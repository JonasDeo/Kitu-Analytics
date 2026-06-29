from fastapi import FastAPI, HTTPException
from sqlalchemy import create_engine, text
import pandas as pd
import os
from datetime import datetime

app = FastAPI(title="Kitu ML Service", version="1.0.0")

DATABASE_URL = os.getenv("DATABASE_URL")
engine = create_engine(DATABASE_URL)

MODEL_VERSION = "v0.1-basic-heuristic"


@app.get("/")
def root():
    return {"status": "Kitu ML service is running"}


@app.get("/health")
def health():
    return {"status": "healthy", "service": "kitu-ml"}


@app.get("/score/{business_id}")
def calculate_score(business_id: int):
    # Pull all transactions for this business
    query = text("""
        SELECT amount, type, transacted_at, balance_after
        FROM transactions
        WHERE business_id = :business_id
        ORDER BY transacted_at ASC
    """)

    with engine.connect() as conn:
        result = conn.execute(query, {"business_id": business_id})
        rows = result.fetchall()

    if not rows:
        raise HTTPException(status_code=404, detail="No transactions found for this business")

    df = pd.DataFrame(rows, columns=["amount", "type", "transacted_at", "balance_after"])
    df["amount"] = df["amount"].astype(float)
    df["transacted_at"] = pd.to_datetime(df["transacted_at"])

    # ── Feature engineering ──

    incoming = df[df["type"] == "incoming"]
    outgoing = df[df["type"].isin(["outgoing", "withdrawal"])]

    total_days = (df["transacted_at"].max() - df["transacted_at"].min()).days or 1
    active_days = df["transacted_at"].dt.date.nunique()

    # 1. Transaction frequency score (how often the business transacts)
    frequency_ratio = active_days / total_days
    transaction_frequency_score = min(frequency_ratio * 100, 100)

    # 2. Cash flow stability (lower volatility = higher score)
    daily_net = df.groupby(df["transacted_at"].dt.date).apply(
        lambda x: x.loc[x["type"] == "incoming", "amount"].sum()
        - x.loc[x["type"].isin(["outgoing", "withdrawal"]), "amount"].sum()
    )
    volatility = daily_net.std() if len(daily_net) > 1 else 0
    mean_flow = daily_net.mean() if len(daily_net) > 0 else 1
    coefficient_of_variation = (volatility / abs(mean_flow)) if mean_flow != 0 else 1
    cash_flow_stability_score = max(0, 100 - min(coefficient_of_variation * 50, 100))

    # 3. Revenue trend (growing, flat, or declining)
    incoming_total = incoming["amount"].sum()
    outgoing_total = outgoing["amount"].sum()
    net_position = incoming_total - outgoing_total

    # 4. Network health (placeholder until guarantor graph is built - Week 2)
    unique_counterparties = df["amount"].count()  # simplified proxy for now
    network_health_score = min((unique_counterparties / 50) * 100, 100)

    # 5. Repayment likelihood - simple heuristic blend (will be ML model in Week 4)
    repayment_likelihood = round(
        (transaction_frequency_score * 0.35)
        + (cash_flow_stability_score * 0.40)
        + (network_health_score * 0.25),
        2
    )

    # Final score scaled to 0-1000
    final_score = int(min(max(repayment_likelihood * 10, 0), 1000))

    # Grade mapping
    if final_score >= 800:
        grade = "A"
    elif final_score >= 650:
        grade = "B"
    elif final_score >= 500:
        grade = "C"
    elif final_score >= 350:
        grade = "D"
    else:
        grade = "F"

    return {
        "business_id": business_id,
        "score": final_score,
        "grade": grade,
        "model_version": MODEL_VERSION,
        "calculated_at": datetime.utcnow().isoformat(),
        "factors": {
            "transaction_frequency_score": round(transaction_frequency_score, 2),
            "cash_flow_stability_score": round(cash_flow_stability_score, 2),
            "network_health_score": round(network_health_score, 2),
            "repayment_likelihood": repayment_likelihood,
            "total_transactions": len(df),
            "active_days": int(active_days),
            "total_days_observed": int(total_days),
            "total_incoming": float(incoming_total),
            "total_outgoing": float(outgoing_total),
            "net_position": float(net_position),
        }
    }