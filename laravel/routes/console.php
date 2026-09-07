<?php

use Illuminate\Support\Facades\Schedule;

// ── Kitu Analytics Scheduled Jobs ────────────────────────────────────────────

// Nightly pre-approval batch — runs at 8pm Tanzania time (UTC+3 = 17:00 UTC)
Schedule::command('kitu:pre-approvals')->dailyAt('17:00');

// Smart reminders — runs every morning at 8am Tanzania time (05:00 UTC)
Schedule::command('kitu:reminders')->dailyAt('05:00');

// Model retraining — runs every Sunday at midnight
Schedule::command('kitu:retrain')->weekly()->sundays()->at('00:00');