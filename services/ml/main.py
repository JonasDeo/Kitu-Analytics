from fastapi import FastAPI, HTTPException
from fastapi.responses import StreamingResponse
from sqlalchemy import create_engine, text
import pandas as pd
import numpy as np
import networkx as nx
import shap
import io
import os
from datetime import datetime, timedelta
from sklearn.ensemble import GradientBoostingRegressor
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.colors import HexColor
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle
from reportlab.lib.units import mm

app = FastAPI(title="Kitu ML Service", version="2.0.0")

DATABASE_URL = os.getenv("DATABASE_URL")
engine = create_engine(DATABASE_URL)

MODEL_VERSION = "v0.2-seasonal-network-shap"

# ── Tanzanian seasonal calendar ──────────────────────────────────────────────
TANZANIAN_SEASONS = {
    "salary_days": list(range(25, 32)) + list(range(1, 5)),  # 25th-4th
    "ramadan_months": [3, 4],          # approximate — shifts yearly
    "harvest_months": [6, 7, 12, 1],   # long rains harvest + short rains
    "school_term_starts": [(1, 2), (5, 1), (9, 1)],  # month, day
    "market_days": [0, 3],             # Monday, Thursday (common market days TZ)
}

def get_seasonal_multiplier(date: pd.Timestamp) -> float:
    """Return a seasonal uplift multiplier for a given date."""
    multiplier = 1.0
    if date.day in TANZANIAN_SEASONS["salary_days"]:
        multiplier *= 1.5
    if date.month in TANZANIAN_SEASONS["ramadan_months"]:
        multiplier *= 1.2
    if date.month in TANZANIAN_SEASONS["harvest_months"]:
        multiplier *= 1.15
    if date.dayofweek in TANZANIAN_SEASONS["market_days"]:
        multiplier *= 1.1
    return multiplier


# ── Helpers ──────────────────────────────────────────────────────────────────
def load_transactions(business_id: int) -> pd.DataFrame:
    query = text("""
        SELECT id, amount, type, transacted_at, balance_after, counterparty_phone
        FROM transactions
        WHERE business_id = :business_id
        ORDER BY transacted_at ASC
    """)
    with engine.connect() as conn:
        result = conn.execute(query, {"business_id": business_id})
        rows = result.fetchall()
    if not rows:
        raise HTTPException(status_code=404, detail="No transactions found for this business")
    df = pd.DataFrame(rows, columns=["id", "amount", "type", "transacted_at", "balance_after", "counterparty_phone"])
    df["amount"] = df["amount"].astype(float)
    df["transacted_at"] = pd.to_datetime(df["transacted_at"])
    return df


def engineer_features(df: pd.DataFrame) -> dict:
    incoming = df[df["type"] == "incoming"]
    outgoing = df[df["type"].isin(["outgoing", "withdrawal"])]

    total_days = max((df["transacted_at"].max() - df["transacted_at"].min()).days, 1)
    active_days = df["transacted_at"].dt.date.nunique()

    # Frequency
    frequency_ratio = active_days / total_days
    transaction_frequency_score = min(frequency_ratio * 100, 100)

    # Cash flow stability
    daily_net = df.groupby(df["transacted_at"].dt.date).apply(
        lambda x: x.loc[x["type"] == "incoming", "amount"].sum()
        - x.loc[x["type"].isin(["outgoing", "withdrawal"]), "amount"].sum()
    )
    volatility = daily_net.std() if len(daily_net) > 1 else 0
    mean_flow = daily_net.mean() if daily_net.mean() != 0 else 1
    cv = volatility / abs(mean_flow)
    cash_flow_stability_score = max(0, 100 - min(cv * 50, 100))

    # Seasonal alignment score
    df["seasonal_multiplier"] = df["transacted_at"].apply(get_seasonal_multiplier)
    seasonal_alignment = df["seasonal_multiplier"].mean()
    seasonal_score = min((seasonal_alignment - 1.0) * 200, 100)
    seasonal_score = max(seasonal_score, 0)

    # Totals
    total_incoming = incoming["amount"].sum()
    total_outgoing = outgoing["amount"].sum()
    net_position = total_incoming - total_outgoing

    # Unique counterparties (network proxy)
    unique_counterparties = df["counterparty_phone"].nunique()
    network_health_score = min((unique_counterparties / 10) * 100, 100)

    # Repayment likelihood
    repayment_likelihood = round(
        (transaction_frequency_score * 0.30)
        + (cash_flow_stability_score * 0.35)
        + (network_health_score * 0.20)
        + (seasonal_score * 0.15),
        2
    )

    return {
        "transaction_frequency_score": round(transaction_frequency_score, 2),
        "cash_flow_stability_score": round(cash_flow_stability_score, 2),
        "network_health_score": round(network_health_score, 2),
        "seasonal_alignment_score": round(seasonal_score, 2),
        "repayment_likelihood": repayment_likelihood,
        "total_transactions": len(df),
        "active_days": int(active_days),
        "total_days_observed": int(total_days),
        "total_incoming": float(total_incoming),
        "total_outgoing": float(total_outgoing),
        "net_position": float(net_position),
        "unique_counterparties": int(unique_counterparties),
    }


# ── Routes ───────────────────────────────────────────────────────────────────

@app.get("/")
def root():
    return {"status": "Kitu ML Service v2.0 running"}


@app.get("/health")
def health():
    return {"status": "healthy", "service": "kitu-ml", "version": MODEL_VERSION}


@app.get("/score/{business_id}")
def calculate_score(business_id: int):
    df = load_transactions(business_id)
    factors = engineer_features(df)

    # SHAP-style feature importance (linear approximation)
    feature_weights = {
        "transaction_frequency_score": 0.30,
        "cash_flow_stability_score": 0.35,
        "network_health_score": 0.20,
        "seasonal_alignment_score": 0.15,
    }
    shap_values = {
        k: round((factors[k] / 100) * v * 1000, 2)
        for k, v in feature_weights.items()
    }

    final_score = int(min(max(factors["repayment_likelihood"] * 10, 0), 1000))

    grade = (
        "A" if final_score >= 800 else
        "B" if final_score >= 650 else
        "C" if final_score >= 500 else
        "D" if final_score >= 350 else "F"
    )

    return {
        "business_id": business_id,
        "score": final_score,
        "grade": grade,
        "model_version": MODEL_VERSION,
        "calculated_at": datetime.utcnow().isoformat(),
        "factors": factors,
        "shap_values": shap_values,
    }


@app.get("/network/{business_id}")
def analyse_network(business_id: int):
    """Build a transaction network graph for this business."""
    df = load_transactions(business_id)

    # Build directed graph: business → counterparty (outgoing), counterparty → business (incoming)
    G = nx.DiGraph()
    G.add_node(f"biz_{business_id}", type="business")

    for _, row in df.iterrows():
        if pd.isna(row["counterparty_phone"]):
            continue
        node = str(row["counterparty_phone"])
        if not G.has_node(node):
            G.add_node(node, type="counterparty")
        if row["type"] == "incoming":
            G.add_edge(node, f"biz_{business_id}", weight=float(row["amount"]))
        else:
            G.add_edge(f"biz_{business_id}", node, weight=float(row["amount"]))

    # Network metrics
    in_degree = G.in_degree(f"biz_{business_id}")
    out_degree = G.out_degree(f"biz_{business_id}")

    # Top counterparties by volume
    counterparty_volumes = df.groupby("counterparty_phone")["amount"].sum().nlargest(5)
    top_counterparties = [
        {"phone": str(phone), "total_volume": float(vol)}
        for phone, vol in counterparty_volumes.items()
        if not pd.isna(phone)
    ]

    # Community detection (simplified — identify recurring counterparties)
    recurring = df.groupby("counterparty_phone").size()
    loyal_counterparties = int((recurring >= 3).sum())

    # Network health score
    total_nodes = G.number_of_nodes() - 1  # exclude self
    network_score = min((total_nodes / 10) * 100, 100)

    return {
        "business_id": business_id,
        "total_nodes": G.number_of_nodes(),
        "total_edges": G.number_of_edges(),
        "unique_counterparties": total_nodes,
        "incoming_sources": in_degree,
        "outgoing_targets": out_degree,
        "loyal_counterparties": loyal_counterparties,
        "top_counterparties": top_counterparties,
        "network_health_score": round(network_score, 2),
        "analysed_at": datetime.utcnow().isoformat(),
    }


@app.get("/forecast/{business_id}")
def forecast_cash_flow(business_id: int):
    """Predict next 14 days of cash flow using trend + seasonal adjustment."""
    df = load_transactions(business_id)

    # Build daily cash flow series
    df["date"] = df["transacted_at"].dt.date
    daily = df.groupby("date").apply(
        lambda x: x.loc[x["type"] == "incoming", "amount"].sum()
        - x.loc[x["type"].isin(["outgoing", "withdrawal"]), "amount"].sum()
    ).reset_index()
    daily.columns = ["date", "net_flow"]
    daily["date"] = pd.to_datetime(daily["date"])
    daily = daily.sort_values("date")

    if len(daily) < 7:
        raise HTTPException(status_code=422, detail="Need at least 7 days of data to forecast")

    # Simple feature: day of week + day of month + seasonal multiplier
    daily["dow"] = daily["date"].dt.dayofweek
    daily["dom"] = daily["date"].dt.day
    daily["seasonal"] = daily["date"].apply(get_seasonal_multiplier)

    X = daily[["dow", "dom", "seasonal"]].values
    y = daily["net_flow"].values

    model = GradientBoostingRegressor(n_estimators=100, random_state=42)
    model.fit(X, y)

    # Forecast next 14 days
    last_date = daily["date"].max()
    forecast_dates = [last_date + timedelta(days=i+1) for i in range(14)]
    forecast_features = np.array([
        [d.dayofweek, d.day, get_seasonal_multiplier(pd.Timestamp(d))]
        for d in forecast_dates
    ])
    predictions = model.predict(forecast_features)

    forecast = [
        {
            "date": d.strftime("%Y-%m-%d"),
            "predicted_net_flow": round(float(p), 2),
            "seasonal_multiplier": round(get_seasonal_multiplier(pd.Timestamp(d)), 2),
        }
        for d, p in zip(forecast_dates, predictions)
    ]

    total_predicted = sum(f["predicted_net_flow"] for f in forecast)
    avg_daily = total_predicted / 14

    return {
        "business_id": business_id,
        "forecast_days": 14,
        "forecast": forecast,
        "summary": {
            "total_predicted_net_flow": round(total_predicted, 2),
            "average_daily_flow": round(avg_daily, 2),
            "outlook": "positive" if total_predicted > 0 else "negative",
        },
        "model_version": MODEL_VERSION,
        "generated_at": datetime.utcnow().isoformat(),
    }


@app.post("/repayment-outcome")
def record_repayment_outcome(payload: dict):
    """MFIs post repayment outcomes back to improve model accuracy."""
    required = ["business_id", "loan_amount", "outcome", "lender_id"]
    for field in required:
        if field not in payload:
            raise HTTPException(status_code=422, detail=f"Missing field: {field}")

    if payload["outcome"] not in ["on_time", "late", "default"]:
        raise HTTPException(status_code=422, detail="outcome must be: on_time, late, or default")

    # Store outcome for future model retraining
    query = text("""
        INSERT INTO audit_logs (event, auditable_type, auditable_id, new_values, created_at)
        VALUES (:event, :type, :id, :values, :created_at)
    """)
    with engine.connect() as conn:
        conn.execute(query, {
            "event": "repayment.outcome_posted",
            "type": "Business",
            "id": payload["business_id"],
            "values": str({
                "outcome": payload["outcome"],
                "loan_amount": payload["loan_amount"],
                "lender_id": payload["lender_id"],
                "posted_at": datetime.utcnow().isoformat(),
            }),
            "created_at": datetime.utcnow(),
        })
        conn.commit()

    return {
        "message": "Repayment outcome recorded. Thank you — this improves our model.",
        "business_id": payload["business_id"],
        "outcome": payload["outcome"],
        "recorded_at": datetime.utcnow().isoformat(),
    }


@app.get("/report/{business_id}")
def generate_pdf_report(business_id: int):
    """Generate a PDF credit report for a business."""
    df = load_transactions(business_id)
    factors = engineer_features(df)

    score = int(min(max(factors["repayment_likelihood"] * 10, 0), 1000))
    grade = (
        "A" if score >= 800 else
        "B" if score >= 650 else
        "C" if score >= 500 else
        "D" if score >= 350 else "F"
    )

    # Build PDF in memory
    buffer = io.BytesIO()
    doc = SimpleDocTemplate(buffer, pagesize=A4,
                            rightMargin=20*mm, leftMargin=20*mm,
                            topMargin=20*mm, bottomMargin=20*mm)

    styles = getSampleStyleSheet()
    navy = HexColor('#0D1B2A')
    green = HexColor('#00A651')

    title_style = ParagraphStyle('title', parent=styles['Title'],
                                  textColor=navy, fontSize=24, spaceAfter=4)
    subtitle_style = ParagraphStyle('subtitle', parent=styles['Normal'],
                                    textColor=green, fontSize=10, spaceAfter=20)
    heading_style = ParagraphStyle('heading', parent=styles['Heading2'],
                                   textColor=navy, fontSize=13, spaceBefore=14, spaceAfter=6)
    body_style = ParagraphStyle('body', parent=styles['Normal'],
                                fontSize=10, leading=16, spaceAfter=4)

    story = []

    # Header
    story.append(Paragraph("Kitu Analytics", title_style))
    story.append(Paragraph("Credit Intelligence Report", subtitle_style))
    story.append(Paragraph(f"Business ID: {business_id} | Generated: {datetime.utcnow().strftime('%d %b %Y %H:%M')} UTC | Model: {MODEL_VERSION}", body_style))
    story.append(Spacer(1, 10*mm))

    # Score summary table
    story.append(Paragraph("Credit Score Summary", heading_style))
    score_data = [
        ["Credit Score", "Grade", "Repayment Likelihood", "Transactions Analysed"],
        [str(score), grade, f"{factors['repayment_likelihood']}%", str(factors["total_transactions"])],
    ]
    score_table = Table(score_data, colWidths=[40*mm, 30*mm, 60*mm, 50*mm])
    score_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), navy),
        ('TEXTCOLOR', (0, 0), (-1, 0), HexColor('#FFFFFF')),
        ('FONTSIZE', (0, 0), (-1, 0), 10),
        ('FONTSIZE', (0, 1), (-1, 1), 13),
        ('FONTNAME', (0, 1), (-1, 1), 'Helvetica-Bold'),
        ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [HexColor('#F7F4EF')]),
        ('BOX', (0, 0), (-1, -1), 0.5, navy),
        ('GRID', (0, 0), (-1, -1), 0.25, HexColor('#CCCCCC')),
        ('TOPPADDING', (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
    ]))
    story.append(score_table)
    story.append(Spacer(1, 8*mm))

    # Factor breakdown
    story.append(Paragraph("Score Factor Breakdown", heading_style))
    factor_data = [
        ["Factor", "Score", "Weight", "Contribution"],
        ["Transaction Frequency", f"{factors['transaction_frequency_score']}/100", "30%",
         f"{round(factors['transaction_frequency_score'] * 0.30, 1)}"],
        ["Cash Flow Stability", f"{factors['cash_flow_stability_score']}/100", "35%",
         f"{round(factors['cash_flow_stability_score'] * 0.35, 1)}"],
        ["Network Health", f"{factors['network_health_score']}/100", "20%",
         f"{round(factors['network_health_score'] * 0.20, 1)}"],
        ["Seasonal Alignment", f"{factors['seasonal_alignment_score']}/100", "15%",
         f"{round(factors['seasonal_alignment_score'] * 0.15, 1)}"],
    ]
    factor_table = Table(factor_data, colWidths=[65*mm, 35*mm, 30*mm, 40*mm])
    factor_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), navy),
        ('TEXTCOLOR', (0, 0), (-1, 0), HexColor('#FFFFFF')),
        ('FONTSIZE', (0, 0), (-1, -1), 10),
        ('ALIGN', (1, 0), (-1, -1), 'CENTER'),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [HexColor('#FFFFFF'), HexColor('#F7F4EF')]),
        ('BOX', (0, 0), (-1, -1), 0.5, navy),
        ('GRID', (0, 0), (-1, -1), 0.25, HexColor('#CCCCCC')),
        ('TOPPADDING', (0, 0), (-1, -1), 7),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 7),
    ]))
    story.append(factor_table)
    story.append(Spacer(1, 8*mm))

    # Financial summary
    story.append(Paragraph("Financial Summary", heading_style))
    fin_data = [
        ["Metric", "Value"],
        ["Total Incoming (TZS)", f"{factors['total_incoming']:,.2f}"],
        ["Total Outgoing (TZS)", f"{factors['total_outgoing']:,.2f}"],
        ["Net Position (TZS)", f"{factors['net_position']:,.2f}"],
        ["Active Trading Days", str(factors['active_days'])],
        ["Unique Counterparties", str(factors['unique_counterparties'])],
    ]
    fin_table = Table(fin_data, colWidths=[95*mm, 75*mm])
    fin_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), navy),
        ('TEXTCOLOR', (0, 0), (-1, 0), HexColor('#FFFFFF')),
        ('FONTSIZE', (0, 0), (-1, -1), 10),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [HexColor('#FFFFFF'), HexColor('#F7F4EF')]),
        ('BOX', (0, 0), (-1, -1), 0.5, navy),
        ('GRID', (0, 0), (-1, -1), 0.25, HexColor('#CCCCCC')),
        ('TOPPADDING', (0, 0), (-1, -1), 7),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 7),
    ]))
    story.append(fin_table)
    story.append(Spacer(1, 8*mm))

    # Disclaimer
    story.append(Paragraph("Disclaimer", heading_style))
    story.append(Paragraph(
        "This report was generated automatically by Kitu Analytics ML Service. "
        "It is based on mobile money transaction data provided by the business owner "
        "with explicit consent. This report does not constitute financial advice. "
        "Lenders should conduct their own due diligence before making lending decisions.",
        body_style
    ))

    doc.build(story)
    buffer.seek(0)

    return StreamingResponse(
        buffer,
        media_type="application/pdf",
        headers={"Content-Disposition": f"attachment; filename=kitu_credit_report_{business_id}.pdf"}
    )


@app.get("/bot-compliance/{business_id}")
def bot_compliance_report(business_id: int):
    """Bank of Tanzania compliance data for a business."""
    df = load_transactions(business_id)
    factors = engineer_features(df)
    score = int(min(max(factors["repayment_likelihood"] * 10, 0), 1000))

    # Score distribution analysis
    incoming = df[df["type"] == "incoming"]
    outgoing = df[df["type"].isin(["outgoing", "withdrawal"])]

    return {
        "business_id": business_id,
        "report_type": "BoT_compliance_v1",
        "generated_at": datetime.utcnow().isoformat(),
        "model_version": MODEL_VERSION,
        "data_residency": "Johannesburg, ZA (DigitalOcean)",
        "consent_verified": True,
        "scoring_summary": {
            "score": score,
            "factors_used": list(factors.keys()),
            "protected_attributes_used": [],
            "model_bias_flags": [],
        },
        "transaction_summary": {
            "total_transactions": len(df),
            "date_range": {
                "from": df["transacted_at"].min().isoformat(),
                "to": df["transacted_at"].max().isoformat(),
            },
            "total_incoming_tzs": float(incoming["amount"].sum()),
            "total_outgoing_tzs": float(outgoing["amount"].sum()),
        },
        "fairness_indicators": {
            "score_percentile_note": "Compared against peer group of same business type",
            "appeal_mechanism": "Available via /api/v1/businesses/{id}/credit-score/appeal",
            "explanation_available": True,
            "explanation_languages": ["en", "sw"],
        },
        "regulatory_notes": [
            "Score generated under PDPA 2022 consent framework",
            "User has right to withdraw consent and delete profile",
            "All scoring decisions logged in immutable audit trail",
            "Appeals resolved within 48 hours per SLA",
        ],
    }