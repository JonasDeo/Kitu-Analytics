<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UssdController extends Controller
{
    // USSD session timeout — 3 minutes
    const SESSION_TTL = 180;

    // ML service URL
    private string $mlUrl;

    public function __construct()
    {
        $this->mlUrl = env('ML_SERVICE_URL', 'http://ml:8001');
    }

    /**
     * Main USSD callback — called by Africa's Talking on every user input.
     * USSD is stateless — we maintain state in Redis using the sessionId.
     */
    public function callback(Request $request)
    {
        $sessionId   = $request->input('sessionId');
        $serviceCode = $request->input('serviceCode');
        $phoneNumber = $request->input('phoneNumber');
        $text        = $request->input('text', '');

        // Normalize phone
        $phone = $this->normalizePhone($phoneNumber);

        // Parse input history — AT sends cumulative input separated by *
        $inputs = $text === '' ? [] : explode('*', $text);
        $level  = count($inputs);

        // Route based on input depth
        $response = $this->route($sessionId, $phone, $inputs, $level);

        return response($response, 200)->header('Content-Type', 'text/plain');
    }

    private function route(string $sessionId, string $phone, array $inputs, int $level): string
    {
        // ── Level 0: Main menu ────────────────────────────────────────────────
        if ($level === 0) {
            return $this->mainMenu();
        }

        $choice = $inputs[0];

        // ── Level 1: Main menu selection ──────────────────────────────────────
        if ($level === 1) {
            return match($choice) {
                '1' => $this->checkScore($phone),
                '2' => $this->cashFlowSummary($phone),
                '3' => $this->applyForLoan($phone),
                '4' => $this->reportIssue($sessionId),
                '5' => $this->registerMenu(),
                default => $this->mainMenu("Chaguo si sahihi. Jaribu tena."),
            };
        }

        // ── Level 2+: Sub-menu handling ───────────────────────────────────────
        return match($inputs[0]) {
            '3' => $this->handleLoanApplication($phone, $inputs, $level),
            '4' => $this->handleIssueReport($sessionId, $phone, $inputs, $level),
            '5' => $this->handleRegistration($sessionId, $phone, $inputs, $level),
            default => $this->mainMenu("Chaguo si sahihi."),
        };
    }

    // ── Menus ──────────────────────────────────────────────────────────────────

    private function mainMenu(string $error = ''): string
    {
        $err = $error ? "\n⚠️ {$error}\n" : '';
        return "CON {$err}Karibu Kitu Analytics 🌟
Huduma za Fedha kwa Biashara Yako

1. Angalia alama ya mkopo
2. Tazama mwenendo wa fedha
3. Omba mkopo
4. Ripoti tatizo
5. Jisajili";
    }

    private function registerMenu(): string
    {
        return "CON Jisajili kwa Kitu Analytics
Ingiza jina lako kamili:";
    }

    // ── Feature handlers ───────────────────────────────────────────────────────

    private function checkScore(string $phone): string
    {
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return "END Hujasajiliwa bado.
Piga *384*KITU# tena na chagua 5 kujisajili.
Au pakua app: kitu.co.tz";
        }

        $business = $user->businesses()->first();

        if (!$business) {
            return "END Una akaunti lakini huna biashara iliyosajiliwa.
Ingia kwenye app kuongeza biashara yako.";
        }

        $score = $business->latestCreditScore;

        if (!$score) {
            // Trigger score calculation
            try {
                $response = Http::timeout(15)->get("{$this->mlUrl}/score/{$business->id}");
                if ($response->successful()) {
                    $data = $response->json();
                    return "END Alama yako ya mkopo:

📊 Alama: {$data['score']} / 1000
🏆 Daraja: {$data['grade']}
💰 Uwezekano wa kulipa: {$data['factors']['repayment_likelihood']}%

Kwa maelezo zaidi, ingia kwenye app yako.
kitu.co.tz";
                }
            } catch (\Exception $e) {}

            return "END Bado huna alama ya mkopo.
Ingia kwenye app kuomba alama yako ya kwanza.
kitu.co.tz";
        }

        $grade       = $score->grade;
        $scoreVal    = $score->score;
        $repayment   = number_format((float)$score->repayment_likelihood, 1);
        $date        = $score->calculated_at->format('d M Y');

        // Grade-based recommendation
        $recommendation = match(true) {
            $scoreVal >= 800 => "✅ Unastahili mkopo mkubwa",
            $scoreVal >= 650 => "✅ Unastahili mkopo",
            $scoreVal >= 500 => "⚠️  Unaweza kuomba mkopo mdogo",
            default          => "❌ Boresha biashara yako kwanza",
        };

        return "END Alama yako ya mkopo:

📊 Alama: {$scoreVal} / 1000
🏆 Daraja: {$grade}
💰 Uwezekano: {$repayment}%
📅 Tarehe: {$date}

{$recommendation}

Maelezo zaidi: kitu.co.tz";
    }

    private function cashFlowSummary(string $phone): string
    {
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return "END Hujasajiliwa. Piga *384*KITU# na chagua 5.";
        }

        $business = $user->businesses()->first();

        if (!$business) {
            return "END Hakuna biashara iliyosajiliwa.";
        }

        // Get transaction summary
        $totalIncoming = $business->transactions()->where('type', 'incoming')->sum('amount');
        $totalOutgoing = $business->transactions()->whereIn('type', ['outgoing', 'withdrawal'])->sum('amount');
        $netPosition   = $totalIncoming - $totalOutgoing;
        $txCount       = $business->transactions()->count();

        // Last 7 days
        $recentIncoming = $business->transactions()
            ->where('type', 'incoming')
            ->where('transacted_at', '>=', now()->subDays(7))
            ->sum('amount');

        $formattedIncoming = $this->formatTZS($totalIncoming);
        $formattedOutgoing = $this->formatTZS($totalOutgoing);
        $formattedNet      = $this->formatTZS($netPosition);
        $formattedRecent   = $this->formatTZS($recentIncoming);

        $netEmoji = $netPosition >= 0 ? '📈' : '📉';

        return "END Mwenendo wa Fedha - {$business->name}

💚 Zinazoingia: TZS {$formattedIncoming}
🔴 Zinazotoka: TZS {$formattedOutgoing}
{$netEmoji} Nafasi: TZS {$formattedNet}

📱 Wiki hii: TZS {$formattedRecent}
📋 Miamala yote: {$txCount}

Maelezo zaidi: kitu.co.tz";
    }

    private function applyForLoan(string $phone): string
    {
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return "END Hujasajiliwa bado.
Piga *384*KITU# na chagua 5 kujisajili.";
        }

        $business = $user->businesses()->first();
        $score    = $business?->latestCreditScore;

        if (!$score) {
            return "CON Bado huna alama ya mkopo.
Tunahitaji kukuhesabu kwanza.

1. Hesabu alama yangu sasa
2. Rudi nyuma";
        }

        if ($score->score < 350) {
            return "END Alama yako ({$score->score}) ni chini sana kwa mkopo sasa hivi.

Jinsi ya kuboresha alama yako:
- Endelea kutumia M-Pesa kwa biashara
- Ongeza wateja wapya
- Tumia app yetu kufuatilia biashara

Jaribu tena baada ya miezi 3.
kitu.co.tz";
        }

        // Calculate recommended loan
        $totalIncoming = $business->transactions()->where('type', 'incoming')->sum('amount');
        $maxLoan       = round($totalIncoming * 0.3);
        $formattedLoan = $this->formatTZS($maxLoan);

        return "CON Alama yako: {$score->score} ({$score->grade})
Mkopo unaostahili: TZS {$formattedLoan}

Chagua benki:
1. CRDB Microfinance
2. FINCA Tanzania
3. Akiba Commercial Bank
4. Rudi nyuma";
    }

    private function handleLoanApplication(string $phone, array $inputs, int $level): string
    {
        if ($level === 2) {
            $bankChoice = $inputs[1];
            $banks = [
                '1' => 'CRDB Microfinance',
                '2' => 'FINCA Tanzania',
                '3' => 'Akiba Commercial Bank',
            ];

            if ($bankChoice === '4') return $this->mainMenu();
            if (!isset($banks[$bankChoice])) return $this->applyForLoan($phone);

            $bank = $banks[$bankChoice];

            // Send SMS notification to lender (in production, trigger actual API)
            $user = User::where('phone', $phone)->first();
            $business = $user?->businesses()->first();
            $score = $business?->latestCreditScore;

            if ($score) {
                try {
                    $sms = new SmsService();
                    $sms->sendAlert(
                        $phone,
                        "Ombi lako la mkopo kwa {$bank} limepokelewa. Nambari ya kufuatilia: KTU-" . strtoupper(substr(md5($phone . time()), 0, 6)) . ". Utawasiliana nawe ndani ya masaa 48."
                    );
                } catch (\Exception $e) {}
            }

            return "END Ombi lako limetumwa kwa {$bank} ✅

Alama yako imetumwa kwa benki.
Utapigiwa simu ndani ya masaa 48.

Kitu Analytics - Tunajenga kesho yako.
kitu.co.tz";
        }

        return $this->applyForLoan($phone);
    }

    private function reportIssue(string $sessionId): string
    {
        return "CON Ripoti tatizo la data yako:

1. Miamala yangu si sahihi
2. Alama yangu ni ya chini sana
3. Tatizo lingine
4. Rudi nyuma";
    }

    private function handleIssueReport(string $sessionId, string $phone, array $inputs, int $level): string
    {
        if ($level === 2) {
            $issueChoice = $inputs[1];

            if ($issueChoice === '4') return $this->mainMenu();

            $issues = [
                '1' => 'Miamala yangu si sahihi',
                '2' => 'Alama yangu ni ya chini sana',
                '3' => 'Tatizo lingine',
            ];

            $issue = $issues[$issueChoice] ?? 'Tatizo lingine';

            // Log the issue
            $user = User::where('phone', $phone)->first();
            if ($user) {
                $business = $user->businesses()->first();
                if ($business) {
                    \App\Models\ScoreAppeal::create([
                        'business_id'    => $business->id,
                        'credit_score_id' => $business->latestCreditScore?->id ?? 1,
                        'reason'         => "USSD Appeal: {$issue}",
                        'status'         => 'pending',
                        'score_before'   => $business->latestCreditScore?->score,
                        'due_at'         => now()->addHours(48),
                    ]);
                }
            }

            // Send confirmation SMS
            try {
                $sms = new SmsService();
                $sms->sendAlert($phone, "Tatizo lako limepokelewa: '{$issue}'. Tutashughulikia ndani ya masaa 48. Asante kwa uvumilivu wako.");
            } catch (\Exception $e) {}

            return "END Tatizo lako limepokelewa ✅

'{$issue}'

Tutashughulikia ndani ya masaa 48.
Utapata SMS ya uthibitisho.

Kitu Analytics
kitu.co.tz";
        }

        return $this->reportIssue($sessionId);
    }

    private function handleRegistration(string $sessionId, string $phone, array $inputs, int $level): string
    {
        if ($level === 2) {
            // Got name — ask for business name
            $name = trim($inputs[1]);
            Cache::put("ussd_reg_{$sessionId}_name", $name, self::SESSION_TTL);

            return "CON Asante, {$name}!
Ingiza jina la biashara yako:";
        }

        if ($level === 3) {
            // Got business name — ask for business type
            $businessName = trim($inputs[2]);
            Cache::put("ussd_reg_{$sessionId}_biz", $businessName, self::SESSION_TTL);

            return "CON Aina ya biashara:
1. Rejareja (duka)
2. Muuzaji (vendor)
3. Huduma
4. Kilimo";
        }

        if ($level === 4) {
            // Got business type — create account
            $name         = Cache::get("ussd_reg_{$sessionId}_name", 'Mtumiaji');
            $businessName = Cache::get("ussd_reg_{$sessionId}_biz", 'Biashara Yangu');
            $typeChoice   = $inputs[3];

            $types = ['1' => 'retail', '2' => 'vendor', '3' => 'service', '4' => 'agricultural'];
            $type  = $types[$typeChoice] ?? 'retail';

            // Check if user already exists
            $existingUser = User::where('phone', $phone)->first();

            if ($existingUser) {
                return "END Una akaunti tayari! 
Ingia kwenye app yako.
kitu.co.tz";
            }

            try {
                // Create user
                $user = User::create([
                    'name'              => $name,
                    'phone'             => $phone,
                    'password'          => \Illuminate\Support\Facades\Hash::make(substr(md5($phone), 0, 8)),
                    'role'              => 'business_owner',
                    'is_verified'       => true,
                    'phone_verified_at' => now(),
                ]);

                // Create business
                $business = $user->businesses()->create([
                    'name'   => $businessName,
                    'type'   => $type,
                    'status' => 'active',
                ]);

                // Send welcome SMS with temp password
                $tempPassword = substr(md5($phone), 0, 8);
                try {
                    $sms = new SmsService();
                    $sms->sendAlert(
                        $phone,
                        "Karibu Kitu Analytics! Akaunti yako imefunguliwa. Biashara: {$businessName}. Nywila ya muda: {$tempPassword}. Ingia: kitu.co.tz"
                    );
                } catch (\Exception $e) {}

                // Clean up session cache
                Cache::forget("ussd_reg_{$sessionId}_name");
                Cache::forget("ussd_reg_{$sessionId}_biz");

                return "END Umesajiliwa! 🎉

Jina: {$name}
Biashara: {$businessName}

SMS ya kuingia imetumwa.
Ingia kwenye app: kitu.co.tz

Asante kwa kuchagua Kitu Analytics!";

            } catch (\Exception $e) {
                return "END Hitilafu imetokea. Tafadhali jaribu tena.
Piga *384*KITU# tena.";
            }
        }

        return $this->registerMenu();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-]/', '', $phone);
        if (str_starts_with($phone, '+255')) return '0' . substr($phone, 4);
        if (str_starts_with($phone, '255')) return '0' . substr($phone, 3);
        return $phone;
    }

    private function formatTZS(float $amount): string
    {
        if ($amount >= 1_000_000) return number_format($amount / 1_000_000, 1) . 'M';
        if ($amount >= 1_000)    return number_format($amount / 1_000, 0) . 'K';
        return number_format($amount, 0);
    }
}