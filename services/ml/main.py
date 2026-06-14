from fastapi import FastAPI

app = FastAPI(title="Kitu ML Service", version="1.0.0")

@app.get("/")
def root():
    return {"status": "Kitu ML service is running"}

@app.get("/health")
def health():
    return {"status": "healthy", "service": "kitu-ml"}