# 🏗️ SME BOOKKEEPING APP — BUILD PLAN
## Feature-Complete MVP, Kuza Business-Class, Tanzanian SME Market

---

## 🎯 OVERVIEW

**Goal**: Build a mobile-first bookkeeping app for Tanzanian SMEs (dukas, retailers, small wholesalers) that replaces the notebook — sales, stock, debts, expenses, and supplier tracking, captured as the owner works, not typed in later.

**Positioning vs. Kuza Business**: Match their core feature set, fix the specific gap their own users complain about (clean split of partial payments into revenue vs. debt), and keep the door open to feed structured business data into a credit-scoring product like [[kitu-analytics]] down the line — but this build stands alone; it doesn't depend on that integration.

> Sequence below is priority order, not a calendar. "Phase 1" ships before "Phase 2" starts — how long each phase takes is a separate conversation.

---

## 🧱 DATA MODEL

```sql
-- Businesses table (multi-branch support from day one)
-- Users table (owner + staff, roles)
-- Branches table (per-location: name, address, active status)
-- Products table (name, SKU/barcode, unit, cost price, sale price, category)
-- Stock_levels table (product x branch, current qty, low-stock threshold)
-- Stock_movements table (immutable log: sale, restock, transfer, adjustment)
-- Sales table (branch, staff, customer, channel, total, timestamp)
-- Sale_items table (sale x product x qty x price)
-- Payments table (sale_id nullable, amount, method, is_partial flag) -- fixes the Kuza complaint
-- Customers table (name, phone, branch)
-- Customer_balances table (running debt/credit per customer, computed from Payments)
-- Suppliers table (name, phone, contact)
-- Supplier_invoices table (supplier, amount, due_date, status)
-- Supplier_payments table (invoice_id, amount, date, settled_from -- cash/mobile money/wallet)
-- Expenses table (branch, category, amount, note, timestamp)
-- Employees table (business, branch, role, permissions)
-- Shifts table (employee, branch, clock_in, clock_out)
-- Reminders table (type: debt/bill/restock, target_id, due_date, sent_at)
-- Reports_cache table (precomputed daily/weekly rollups per branch for fast dashboard loads)
```

**Key design decision**: `Payments` is separate from `Sales`. A sale of 200,000 TZS with only 150,000 paid creates one Sale (200,000) and one Payment (150,000) — the customer's balance shows 50,000 owed automatically, and total revenue still reads correctly as 200,000. This is the exact bug Kuza Business users are asking them to fix.

---

## 📱 CORE MODULES — PRIORITY ORDER

### Phase 1: Foundation (must work before anything else does)
- [ ] **Auth & business setup** — phone-number login (OTP), business profile creation, single branch to start
- [ ] **Product catalog** — add/edit products, set cost + sale price, optional barcode field
- [ ] **Sales capture** — the core loop: pick product(s), quantity, price, customer (optional), payment amount received. This is the screen used dozens of times a day — it needs to be the fastest thing in the app.
- [ ] **Stock auto-decrement** — every sale item reduces stock_levels in real time; every restock increases it
- [ ] **Basic expense logging** — category, amount, note, one tap

**Deliverable**: An owner can record a full day of sales and expenses and see accurate stock levels drop as they sell.

### Phase 2: Money owed, both directions
- [ ] **Partial payment handling** — split any sale into paid + owed automatically (see data model above)
- [ ] **Customer ledger** — per-customer balance, payment history, one-tap "send reminder" (SMS)
- [ ] **Supplier invoices** — log what's owed to suppliers, due dates
- [ ] **Supplier payments** — mark invoices paid, track partial settlement same as customer side

**Deliverable**: Owner can see "who owes me" and "who I owe" without touching a notebook.

### Phase 3: Multi-branch & staff
- [ ] **Branch management** — add branches, assign staff, switch context in-app
- [ ] **Per-branch stock & sales views** — filter every report by branch or see combined
- [ ] **Employee roles & permissions** — e.g., staff can record sales but not delete them or see profit margins
- [ ] **Shift tracking** — clock in/out, per-staff sales attribution

**Deliverable**: Owner running 2+ shops can monitor all of them without being physically present, and know which staff member did what.

### Phase 4: Intelligence layer
- [ ] **Low-stock alerts** — threshold-based, push/SMS notification
- [ ] **Smart reminders** — auto-generated for overdue customer debts, upcoming supplier bills, low stock — no manual setup required
- [ ] **Insights dashboard** — best sellers, slow movers, profit margin by product, cash flow at a glance
- [ ] **Reports** — daily/weekly/monthly rollups, exportable

**Deliverable**: The app starts telling the owner things, not just recording what they tell it.

### Phase 5: Polish & platform reach
- [ ] **Web/PC access** — same account, browser-based dashboard (matches Kuza's cross-platform reach)
- [ ] **Offline-first sync** — critical for low-connectivity areas; queue actions locally, sync when back online
- [ ] **Multi-language toggle** — Swahili-first, but with English available (a gap Kuza's own users have flagged)
- [ ] **Data export / backup** — CSV or PDF export of any ledger

---

## 🛠️ SUGGESTED STACK

Keeping this consistent with the approach used for [[kitu-analytics]] where it makes sense, since both target the same market and infrastructure:

- **Frontend**: React (PWA, offline-first) or React Native if a native app is preferred from the start
- **Backend**: Laravel or a lightweight Node/Express API — either works; Laravel gives you fast CRUD scaffolding for this kind of ledger-heavy app
- **Database**: PostgreSQL (row-level constraints matter here — balances must never go negative unexpectedly)
- **Sync**: Local SQLite/IndexedDB cache with a conflict-resolution strategy for offline sales entry
- **Notifications**: SMS via Africa's Talking (same vendor pattern as Kitu's USSD/SMS work) for reminders
- **Hosting**: DigitalOcean, Johannesburg region, for latency reasons

---

## 🔑 DIFFERENTIATION OPPORTUNITIES

1. **Fix the partial-payment bug** — this is a specific, named complaint from Kuza's own user reviews. Solving it cleanly is a real wedge.
2. **Faster sales-entry screen** — since this is the highest-frequency action in the app, shaving seconds off it compounds daily.
3. **Genuine bilingual UX from day one** — another gap their users have explicitly asked for.
4. **Structured data by design** — if a credit-scoring integration is ever wanted later, having clean, well-typed sales/stock/debt tables (rather than notebook-style free text) makes that a data export, not a re-architecture.

---

## 📊 SUCCESS METRICS FOR MVP

- Sales entry takes <10 seconds for a single-item cash sale
- Stock levels stay accurate to actual counts (spot-check against physical inventory)
- Customer/supplier balances always reconcile — sum of unpaid amounts matches ledger
- Works usably on a low-end Android device on 3G
- Offline sales entry survives a full day without connectivity and syncs cleanly

---

**Same market, same owners, same notebook problem — build the thing that finally replaces it. 🚀**
