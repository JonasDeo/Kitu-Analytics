# Kitu Analytics — Build Plan v3
## From code-complete to first paying lender

*Replaces `MVP-Build-Plan.md` and `updated-MVP-Build-Plan.md`. Written against the state of `main` as of September 2026, not against a blank repo.*

---

## Verdict on the two existing plans

**`updated-MVP-Build-Plan.md` wins, and it isn't close.** Keep it in the repo as a historical artifact, but stop planning from it.

What the updated plan got right that the original got badly wrong:

| Decision | Original plan | Updated plan | Who was right |
|---|---|---|---|
| Feature-phone users | Ignored — assumed smartphones and stable internet | USSD `*384*8562#` as a Week 1 must-have | Updated. Smartphone penetration is not the addressable market; SIM ownership is. |
| Language | English-only, unmentioned | Swahili-first, i18n as a must-have | Updated. |
| Money | Zero monetisation anywhere in six weeks | Pay-per-query API, revenue_events table, price sheet | Updated. A demo with no revenue mechanism is a science project. |
| Consent & appeals | One line: "GDPR compliance" | PDPA 2022 consent records, score appeals, audit trail, BoT endpoint | Updated. GDPR is the wrong law for Tanzania — that line alone shows the original was a template. |
| Explainability | "Explainable AI features" | Score explanation panel, reason codes, appeal flow | Updated. |

What the original plan contained that should never have been in a six-week MVP, and is still quietly sitting in the updated plan: **blockchain credit history** (Week 4), **micro-insurance with risk pools and claims processing** (Week 5), **banking API integrations with "major Tanzanian banks"** (Week 5), and a roadmap ending in "IPO/acquisition preparation". None of these are build items. They are pitch-deck ornaments, and they cost you credibility with anyone technical who reads the plan.

**But the updated plan has one fatal flaw of its own: its status table is wrong.** It says Week 1 is missing CI/CD and USSD. Both exist in the repo — `.github/workflows/ci.yml` and `UssdController.php` with a passing feature test. It says Week 3 is missing PWA/offline; `public/sw.js`, `public/offline.html` and `utils/offlineQueue.ts` all exist. The `README.md` roadmap is worse: it lists OCR parsing, i18n, PWA offline, the employee module and CI/CD as unchecked — every one of them is in the code.

Your documentation is describing a project three months behind the one you actually built. For a repo whose main audience right now is investors and a technical due-diligence reader, that is the single most damaging file in it.

---

## What's actually in the repo

Measured, not estimated:

| Component | Size | Notes |
|---|---|---|
| Laravel API (`laravel/app`) | ~4,200 lines | 22 controllers, 26 models, 1 policy, 3 scheduled commands |
| ML service (`services/ml/main.py`) | 1,291 lines | 14 endpoints, one file |
| React frontend (`frontend/src`) | ~3,600 lines | 7 pages, en/sw locales, service worker |
| Database | 26 tables | 31 migrations, June 14 → September 14 |
| Tests | 430 lines PHP, 9 lines TS | 4 real feature tests; zero ML tests |
| Commits | 27 | Across roughly three months |

**Built beyond what either plan asked for:** the entire bookkeeping module (branches, products, stock, sales with partial payments, customers, suppliers, expenses, reports — 9 controllers, 13 tables), employees and shifts, WhatsApp webhook, photo OCR, the dual-source enhanced score, smart SMS reminders, nightly pre-approval batch.

**Genuinely missing:** all of Week 6. No production deployment, no monitoring, no load testing, no security review, no demo dataset with a narrative. Plus meaningful test coverage — 430 lines of tests against ~8,000 lines of application code, and nothing at all covering the scoring maths, which is the one part where a silent error is invisible and expensive.

### The honest read

You over-delivered on features and under-delivered on everything that makes features trustworthy. That is the normal failure mode for a solo technical founder and it is much easier to fix than the reverse. Nobody can build you 26 tables in a week; anyone can be talked into skipping a back-test.

The three-month elapsed time against a "6-week sprint" heading is fine — that ratio is normal for one person — but stop calling it a six-week sprint in a document an investor will read. Call it what it is.

---

## What changes in this plan

The remaining risk in Kitu is **not build risk**. You have proven you can ship. The remaining risks are, in order:

1. **The score has never been validated against a real repayment outcome.** Not once. `repayment_likelihood` is a hand-weighted average of six signals you chose. It might be excellent. Nobody knows, including you, and a lender's risk officer will ask this in the first ten minutes.
2. **The parser has never been measured against real SMS.** You don't know its failure rate on actual Vodacom M-Pesa messages from actual shops, or your OCR accuracy on a photo of a cracked phone screen in a dark duka.
3. **Nothing is deployed.** There is no uptime figure because there is no server.
4. **The claims in the repo don't match the code**, in both directions — the README undersells what's built and the monetisation table says "Live" for revenue lines that, as far as the code shows, have never been invoiced to a real MFI.

So this plan spends six weeks on validation, deployment and truth, not on features. **No new feature work for six weeks.** If that feels wrong, that feeling is the thing to be suspicious of.

---

## WEEK 1 — Truth and correctness
*Theme: make the repo and the maths honest*

**Day 1–2: fix what's broken**
- [ ] `services/ml/main.py` line in `/train/{business_id}`: `joblib.dump(cache_path.__str__(), cache_path)` saves the file path instead of the model. Forecasts break on the next container restart. One-word fix.
- [ ] `load_repayment_outcomes()` uses `eval()` on a value read from `audit_logs`. That is arbitrary code execution inside the ML container. Replace with `json.loads`.
- [ ] Put a shared-secret header between Laravel and FastAPI, and **remove `ports: 8001:8001`** from `docker-compose.yml` for production. Right now `/pre-approvals` returns names, phone numbers, locations and scores to anyone who can reach that port, and `/report/{id}` hands over a full PDF credit file for any integer.
- [ ] In-memory model cache never expires — only the disk path checks the 24-hour window. Store a load timestamp.

**Day 3–4: one source of truth for the score**
- [ ] The score uses weights 25/30/15/10/10/10. The SHAP block claims 30/35/20/15. The PDF prints 30/35/20/15. Extract one weight table, import it in all three places.
- [ ] Delete the synthetic SHAP block. It fits a linear model to 200 rows of random numbers and explains the line it just recovered — for a linear model SHAP reduces to `weight × (value − 50)`. Replace it with an honest points breakdown. Keep the word SHAP for when the repayment classifier has real training data.
- [ ] Add a minimum-history gate: below ~30 days observed and ~20 transactions, return `insufficient_data` instead of a grade. Today a two-day-old account can score an A on transaction frequency.
- [ ] Extract `score_and_grade()` — the clamp-and-band logic is copy-pasted in four endpoints.
- [ ] Decide which of `/score` and `/score-bookkeeping` is canonical. Two endpoints returning different numbers for the same business, both called "score", both stamped with the same model version, is a due-diligence finding.

**Day 5: tests for the maths**
- [ ] Fixed synthetic frames: the steady shop, the break-even shop, the spiky shop, the two-transaction shop, the shop with 80% round numbers. Assert the score each should get. Every one of these is currently a known weakness — turn them into failing tests you can point at.
- [ ] Wire the ML service into `ci.yml`. It isn't there.

**Day 6–7: make the documents true**
- [ ] Rewrite `README.md` against the actual code. Every roadmap item that exists gets checked. Delete the monetisation "Status" column until a real invoice has been paid, or change "Live" to "Built, not yet billed" — an investor who discovers that word was aspirational will discount everything else you said.
- [ ] Archive both old build plans into `docs/history/`. Keep this file at the root.
- [ ] Add a LICENSE and a repo description. A repo with no description reads as abandoned.

**Deliverable:** the code does what the docs say, the score explains itself accurately, and the two security holes are shut.

---

## WEEK 2 — Deploy and observe
*Theme: a URL that stays up*

- [ ] DigitalOcean droplet, Johannesburg region (the ML service already claims this in `/bot-compliance` — make it true). Docker Compose, Nginx, Let's Encrypt, a real domain.
- [ ] Private network for `postgres`, `redis` and `ml`. Only Nginx exposed.
- [ ] Automated nightly `pg_dump` to Spaces, and **restore the backup once** to prove it works.
- [ ] Sentry on Laravel, FastAPI and React. Uptime monitoring with SMS alerts to you.
- [ ] Load test: `/score` and `/pre-approvals` with a business carrying 10,000 transactions. `load_transactions()` has no `LIMIT` and `/pre-approvals` runs an N+1 query with a fresh connection per lead — both will fall over, and you want to know at what number.
- [ ] Rate limits on the lender API, per key.
- [ ] Verify the scheduled commands actually fire: `kitu:pre-approvals` 20:00, `kitu:reminders` 08:00, `kitu:retrain` Sunday.

**Deliverable:** a production URL, a real uptime number, and an error inbox.

---

## WEEK 3 — Real data
*Theme: the parser meets Tanzania*

This is the week that produces numbers nobody currently has.

- [ ] Recruit 20 real shop owners in Moshi or Mwanza. Not friends running demo accounts — actual dukas.
- [ ] Collect their genuine M-Pesa SMS history, with signed consent through the existing `ConsentController` flow. This is your first real test of whether the consent UX is usable by the target user.
- [ ] **Measure the parser.** What percentage of real messages parse? Which formats fail? Vodacom changes its SMS wording; your regexes assume one phrasing. Log every unparsed line.
- [ ] **Measure the OCR.** Photograph real screens — cracked, dim, glare, Swahili keyboard. What's the accuracy? This number goes in the investor deck either way; a measured 71% beats an unmeasured claim of "OCR support".
- [ ] Fix the silent-date bug: when `_extract_date` fails, the transaction is stamped with upload time. A shop uploading six months of history gets six months of transactions dated today, which collapses `total_days_observed` to 1 and corrupts the grade. Fail loudly instead.
- [ ] Sit with three of them while they use the dashboard. Watch, don't explain. Note every place they stop.
- [ ] Test USSD on a real feature phone on a real network. The 3-second timeout is not theoretical.

**Deliverable:** parser accuracy %, OCR accuracy %, 20 real transaction histories, and a list of UX failures observed rather than imagined.

---

## WEEK 4 — The back-test
*Theme: the only week that decides whether Kitu is a business*

Everything else in this plan is hygiene. This is the thesis.

- [ ] Sign one MFI to a data-sharing pilot — no money, no integration, just their historical loan book: borrower phone numbers, loan amounts, and repayment outcomes for loans already closed.
- [ ] Score those borrowers using the transaction history from *before* each loan was issued.
- [ ] Compare your score to what actually happened. The question is simple: **do defaulters score lower than on-time repayers?** Report separation, AUC, and the default rate in each grade band.
- [ ] If the answer is yes, that chart is your entire pitch and everything else is supporting material.
- [ ] If the answer is no, you have found it in week 4 of a pilot instead of month 8 of a portfolio, and you re-weight using the outcomes as labels — which is exactly what `/train-repayment-model` was built for. Lower its threshold from 10 outcomes (which is far too few to mean anything) to a real number once you know how many you have.
- [ ] Post every outcome through `/repayment-outcome` so the audit trail carries the pilot.

**Deliverable:** one chart showing default rate by grade band, on real loans. Or a corrected model and an honest account of the first attempt.

---

## WEEK 5 — The demo
*Theme: one story, told end to end, on real data*

- [ ] Pick one real pilot business as the protagonist. Name, shop, city, actual numbers.
- [ ] Script the full lifecycle, no slides: SME registers → grants consent → uploads SMS by photo → sees a score with a plain-Swahili explanation → appeals one factor → lender's nightly batch picks them up → lender queries the API → PDF report → loan issued → outcome posted → model retrains.
- [ ] Run it on the production URL, on the real dataset, in Swahili, with the USSD path shown on an actual feature phone. The feature phone is the moment that lands — nobody else demoing fintech in Dar is holding one.
- [ ] Refactor `DashboardPage.tsx` (1,381 lines) only as far as the demo path requires. Not a rewrite — just enough that you can change a number on stage without fear.
- [ ] Record it. Live demos fail on hotel wifi.

**Deliverable:** a 7-minute recorded walkthrough and a live version you can run on demand.

---

## WEEK 6 — The ask
*Theme: numbers that survive a follow-up question*

- [ ] Assemble the metrics you actually have. Real: parser accuracy, OCR accuracy, back-test separation, 20 pilot users, uptime since deployment, response times under load. Delete every invented figure from the old plans — "95% prediction accuracy", "$10K revenue pipeline", "4.5/5 satisfaction" are worse than no number, because the first person who asks how you measured them gets a bad answer.
- [ ] Unit economics on one page: cost to score a business, price per query, gross margin, how many MFI queries per month to break even on the droplet and Africa's Talking bill.
- [ ] Get the LOI signed with the pilot MFI, with the price sheet attached.
- [ ] Security pass: dependency audit, a real penetration attempt against the lender API with a wrong key, confirm PDPA consent withdrawal actually deletes.
- [ ] Write down what you'd do with the money and for how long it lasts.

**Deliverable:** a demo, a signed LOI, a validation chart, and a true metrics page.

---

## Cut list

Delete from all planning documents. Not "later" — gone, until there is a customer asking and paying:

- Blockchain credit history / immutable user-owned profiles
- Micro-insurance: risk pools, premium calculation, claims automation, peer-to-peer insurance
- Weather risk modelling
- Banking API integrations with major Tanzanian banks
- Kenya, Uganda, Rwanda expansion
- White-label platform, API marketplace
- "IPO/acquisition preparation"

Each of these in a build plan signals that the plan was written to impress rather than to be executed. You have a real product; it doesn't need them.

---

## The one metric that matters

Everything in this document reduces to a single question a lender's risk officer will ask you:

> *"On the loans you scored, what was the default rate in grade A versus grade D?"*

Today you cannot answer it. Week 4 exists so that in six weeks you can. Until then, every other number — 26 tables, 14 endpoints, 8,000 lines — is input, not evidence.
