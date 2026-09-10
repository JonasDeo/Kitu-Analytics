# Kitu Analytics

> Credit intelligence for African SMEs — built for the Tanzanian market.

Kitu Analytics turns M-Pesa transaction history and bookkeeping data into credit scores that microfinance institutions can act on. SMEs who have never had a bank account get a fair, explainable credit score. Lenders get a pay-per-query API with pre-approved lead lists delivered every morning.

---

## What's in this repo

```
kitu-analytics/
├── laravel/        # Laravel 11 API (PHP 8.4)
├── services/ml/     # FastAPI ML service (Python 3.11)
├── frontend/        # React 18 + TypeScript dashboard
├── docker/          # Nginx + PHP Dockerfiles
└── docker-compose.yml
```

## Stack

| Layer | Technology |
|---|---|
| API | Laravel 11, PHP 8.4, Sanctum |
| ML | FastAPI, scikit-learn, NetworkX, SHAP, pandas |
| Database | PostgreSQL 15, Redis 7 |
| Frontend | React 18, TypeScript, Tailwind CSS, Recharts |
| Infrastructure | Docker, Docker Compose, Nginx |
| SMS / USSD | Africa's Talking (`*384*8562#`) |

---

## Running locally

### Prerequisites
- Docker Desktop
- Git

### Setup

```bash
git clone https://github.com/YOUR_USERNAME/kitu-analytics.git
cd kitu-analytics

# Copy environment files
cp laravel/.env.example laravel/.env
cp services/ml/.env.example services/ml/.env

# Fill in your credentials in laravel/.env:
# - AT_API_KEY (Africa's Talking)
# - DB_PASSWORD

# Start all 5 containers
docker compose up --build

# In a second terminal — run migrations and seed data
docker exec kitu_php php /var/www/html/artisan key:generate
docker exec kitu_php php /var/www/html/artisan migrate
docker exec kitu_php php /var/www/html/artisan db:seed --class=TransactionSeeder

# Start the React frontend
cd frontend
npm install
npm start
```

### URLs

| Service | URL |
|---|---|
| React dashboard | http://localhost:3000 |
| Laravel API | http://localhost:8000/api/v1 |
| FastAPI ML | http://localhost:8001 |
| API health check | http://localhost:8000/api/v1/health |

---

## Key API endpoints

### SME (Bearer token auth)

```
POST /api/v1/auth/register
POST /api/v1/auth/verify-otp
POST /api/v1/auth/login
GET  /api/v1/businesses
POST /api/v1/businesses/{id}/credit-score/request
POST /api/v1/businesses/{id}/credit-score/enhanced
GET  /api/v1/businesses/{id}/forecast
GET  /api/v1/businesses/{id}/network
GET  /api/v1/businesses/{id}/fraud
GET  /api/v1/businesses/{id}/credit-report (PDF)
```

### Lender (X-Lender-API-Key header)

```
GET  /api/v1/lender/credit-score/{phone}
GET  /api/v1/lender/business-profile/{phone}
GET  /api/v1/lender/pre-approvals
GET  /api/v1/lender/portfolio
POST /api/v1/lender/repayment-outcome
GET  /api/v1/lender/report/{phone} (PDF)
```

### Bookkeeping (Bearer token auth)

```
POST /api/v1/bk/branches
POST /api/v1/bk/products
POST /api/v1/bk/sales
POST /api/v1/bk/sales/{id}/pay
GET  /api/v1/bk/reports/daily
GET  /api/v1/bk/reports/summary
GET  /api/v1/bk/reports/debtors
```

### USSD

```
POST /api/v1/ussd/callback  ← Africa's Talking callback
Shortcode: 3848562#
```

---

## ML service endpoints

```
GET  /score/{business_id}              # M-Pesa credit score
GET  /score-bookkeeping/{business_id}  # Enhanced score (M-Pesa + bookkeeping)
GET  /forecast/{business_id}           # 14-day cash flow forecast
GET  /network/{business_id}            # Transaction network graph
GET  /fraud/{business_id}              # Fraud detection
GET  /report/{business_id}             # PDF credit report
GET  /bot-compliance/{business_id}     # BoT compliance data
GET  /model-status                     # ML pipeline status
POST /repayment-outcome                # Log loan outcome (feeds model retraining)
POST /train-repayment-model            # Train classifier on accumulated outcomes
```

---

## Scheduled jobs

```bash
# Run manually
docker exec kitu_php php /var/www/html/artisan kitu:pre-approvals  # Nightly lead batch
docker exec kitu_php php /var/www/html/artisan kitu:reminders       # Smart SMS reminders
docker exec kitu_php php /var/www/html/artisan kitu:retrain         # Model retraining

# Automatic schedule (production)
# kitu:pre-approvals  → daily at 20:00 EAT
# kitu:reminders      → daily at 08:00 EAT
# kitu:retrain        → every Sunday midnight
```

---

## Database schema (26 tables)

**Credit scoring**: users, businesses, transactions, credit_scores,
score_explanations, score_appeals, audit_logs, consent_records,
guarantor_relationships, alerts, lenders, revenue_events

**Bookkeeping**: branches, products, stock_levels, stock_movements,
sales, sale_items, customers, customer_balances, bk_payments,
suppliers, supplier_invoices, supplier_payments, bk_expenses

---

## Architecture

```
        React PWA          USSD 3848562#
             │                    │
             └──────────┬─────────┘
                         │
                  Laravel 11 API
                         │
        ┌────────────────┼────────────────┐
        │                │                 │
   PostgreSQL          Redis           FastAPI ML
        │                                  │
        └──────────── shared ──────────────┘
                         │
                Africa's Talking
                  (SMS + USSD)
```

---

## Key design decisions

**Partial payment fix**: `Payments` is separate from `Sales`. A TZS 200,000 sale with TZS 150,000 paid creates one Sale (TZS 200,000) and one Payment (TZS 150,000). Revenue reads correctly as TZS 200,000. Customer balance shows TZS 50,000 owed. This fixes the specific bug Kuza Business users complain about.

**USSD-first reach**: `*384*8562#` lets feature phone users check scores, view cash flow, apply for loans, and self-register — no smartphone required. This expands the addressable market beyond smartphone owners.

**Dual data sources**: The enhanced credit score combines M-Pesa SMS transaction history with structured bookkeeping data. A business using both gets `data_quality: high` and a stronger score signal than either source alone.

**Immutable audit trail**: Every scoring decision is logged with model version, input features, output score, requesting lender, and a SHA-256 hash chain. Required for Bank of Tanzania regulatory conversations.

---

## Compliance

- PDPA 2022 (Tanzania Personal Data Protection Act) — consent framework built in
- Bank of Tanzania — compliance report endpoint at `/businesses/{id}/bot-compliance`
- Fair lending — score distribution by business type tracked, no protected attributes used
- Right to appeal — 48-hour SLA on score disputes

---

## Monetisation

| Tier | Price | Status |
|---|---|---|
| SME Free | Free | Live |
| Credit score query (MFI) | TZS 2,500/query | Live (manual invoicing) |
| Pre-approval batch (MFI) | TZS 2,500/lead | Live (manual invoicing) |
| PDF credit report (MFI) | TZS 5,000/report | Live (manual invoicing) |
| MFI Starter SaaS | TZS 200,000/month | Planned |
| Payment processor | Selcom (TZS) | Planned |

---

## Roadmap

- [ ] Selcom payment integration
- [ ] WhatsApp onboarding bot
- [ ] OCR photo-to-SMS parsing
- [ ] PWA offline support
- [ ] Employee/staff module
- [ ] CI/CD (GitHub Actions)
- [ ] DigitalOcean production deployment
- [ ] i18n framework (react-i18next)