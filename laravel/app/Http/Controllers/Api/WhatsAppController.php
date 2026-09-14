<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class WhatsAppController extends Controller
{
    private string $verifyToken;
    private string $accessToken;
    private string $phoneNumberId;
    private string $mlUrl;

    public function __construct()
    {
        $this->verifyToken   = env('WHATSAPP_VERIFY_TOKEN', 'kitu_verify_token');
        $this->accessToken   = env('WHATSAPP_ACCESS_TOKEN', '');
        $this->phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID', '');
        $this->mlUrl         = env('ML_SERVICE_URL', 'http://ml:8001');
    }

    /**
     * Webhook verification — Meta calls this when you set up the webhook
     */
    public function verify(Request $request)
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $this->verifyToken) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Webhook handler — receives incoming WhatsApp messages
     */
    public function webhook(Request $request)
    {
        $body = $request->all();

        // Verify it's from Meta
        if (!isset($body['object']) || $body['object'] !== 'whatsapp_business_account') {
            return response()->json(['status' => 'ignored']);
        }

        foreach ($body['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // Handle incoming messages
                foreach ($value['messages'] ?? [] as $message) {
                    $this->handleMessage($message, $value['metadata'] ?? []);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleMessage(array $message, array $metadata): void
    {
        $from    = $message['from'];           // WhatsApp phone number
        $msgId   = $message['id'];
        $type    = $message['type'];

        // Normalize phone
        $phone = $this->normalizePhone($from);

        // Handle different message types
        match($type) {
            'text'  => $this->handleText($from, $phone, $message['text']['body'] ?? ''),
            'image' => $this->handleImage($from, $phone, $message['image'] ?? []),
            default => $this->sendMessage($from, "Samahani, tunashughulikia maandishi na picha tu kwa sasa. Andika *msaada* kwa chaguo zote."),
        };
    }

    private function handleText(string $waId, string $phone, string $text): void
    {
        $text  = trim(strtolower($text));
        $user  = User::where('phone', $phone)->first();
        $state = Cache::get("wa_state_{$waId}", 'idle');

        // ── Command routing ───────────────────────────────────────────────────
        if (in_array($text, ['habari', 'hello', 'hi', 'hujambo', 'start', 'anza'])) {
            $this->sendWelcome($waId, $user);
            return;
        }

        if (in_array($text, ['msaada', 'help', 'menu'])) {
            $this->sendMenu($waId, $user);
            return;
        }

        if (in_array($text, ['alama', 'score', '1'])) {
            $this->sendScore($waId, $phone, $user);
            return;
        }

        if (in_array($text, ['fedha', 'cash flow', 'mwenendo', '2'])) {
            $this->sendCashFlow($waId, $phone, $user);
            return;
        }

        if (in_array($text, ['sajili', 'register', 'jisajili', '5'])) {
            Cache::put("wa_state_{$waId}", 'awaiting_name', 300);
            $this->sendMessage($waId, "Karibu Kitu Analytics! 🌟\n\nTafadhali andika *jina lako kamili* kuanza usajili.");
            return;
        }

        // ── Registration flow ─────────────────────────────────────────────────
        if ($state === 'awaiting_name') {
            Cache::put("wa_reg_name_{$waId}", $text, 300);
            Cache::put("wa_state_{$waId}", 'awaiting_biz', 300);
            $this->sendMessage($waId, "Asante, *{$text}*! 👋\n\nSasa andika *jina la biashara yako*:");
            return;
        }

        if ($state === 'awaiting_biz') {
            Cache::put("wa_reg_biz_{$waId}", $text, 300);
            Cache::put("wa_state_{$waId}", 'awaiting_type', 300);
            $this->sendMessage($waId,
                "Biashara: *{$text}* ✅\n\nChagua aina ya biashara:\n\n" .
                "1️⃣ Rejareja (duka)\n2️⃣ Muuzaji\n3️⃣ Huduma\n4️⃣ Kilimo\n\n" .
                "Andika nambari (1-4):"
            );
            return;
        }

        if ($state === 'awaiting_type') {
            $types   = ['1' => 'retail', '2' => 'vendor', '3' => 'service', '4' => 'agricultural'];
            $type    = $types[$text] ?? 'retail';
            $name    = Cache::get("wa_reg_name_{$waId}", 'Mtumiaji');
            $bizName = Cache::get("wa_reg_biz_{$waId}", 'Biashara Yangu');

            // Check existing
            if (User::where('phone', $phone)->exists()) {
                Cache::forget("wa_state_{$waId}");
                $this->sendMessage($waId, "Una akaunti tayari! 🎉\n\nAndika *alama* kuona alama yako ya mkopo.");
                return;
            }

            // Create user + business
            $user = User::create([
                'name'              => ucwords($name),
                'phone'             => $phone,
                'password'          => Hash::make(substr(md5($phone . time()), 0, 8)),
                'role'              => 'business_owner',
                'is_verified'       => true,
                'phone_verified_at' => now(),
            ]);

            $user->businesses()->create([
                'name'   => ucwords($bizName),
                'type'   => $type,
                'status' => 'active',
            ]);

            Cache::forget("wa_state_{$waId}");
            Cache::forget("wa_reg_name_{$waId}");
            Cache::forget("wa_reg_biz_{$waId}");

            $this->sendMessage($waId,
                "🎉 *Umesajiliwa kikamilifu!*\n\n" .
                "👤 Jina: {$user->name}\n" .
                "🏪 Biashara: {$bizName}\n\n" .
                "Sasa tuma picha ya SMS zako za M-Pesa au andika *alama* kupata alama yako ya mkopo.\n\n" .
                "Unaweza pia ingia kwenye app: kitu.co.tz"
            );
            return;
        }

        // Default — unrecognized input
        $this->sendMenu($waId, $user);
    }

    private function handleImage(string $waId, string $phone, array $image): void
    {
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            $this->sendMessage($waId, "Tafadhali sajili kwanza. Andika *sajili* kuanza.");
            return;
        }

        $business = $user->businesses()->first();
        if (!$business) {
            $this->sendMessage($waId, "Hujasajili biashara bado. Andika *sajili*.");
            return;
        }

        $this->sendMessage($waId, "⏳ Ninaangalia picha yako ya M-Pesa... Subiri sekunde chache.");

        // Download image from Meta
        try {
            $mediaId  = $image['id'];
            $mediaUrl = $this->getMediaUrl($mediaId);

            if (!$mediaUrl) {
                $this->sendMessage($waId, "Samahani, sikuweza kupakua picha. Jaribu tena.");
                return;
            }

            // Download image bytes
            $imageBytes = Http::withToken($this->accessToken)->get($mediaUrl)->body();

            // Send to OCR endpoint
            $ocrResponse = Http::timeout(30)->attach(
                'file',
                $imageBytes,
                'mpesa_screenshot.jpg',
                ['Content-Type' => 'image/jpeg']
            )->post("{$this->mlUrl}/ocr/parse-mpesa");

            if ($ocrResponse->failed()) {
                $this->sendMessage($waId, "Samahani, OCR imeshindwa. Jaribu tena au andika SMS yako ya M-Pesa kwa maandishi.");
                return;
            }

            $data  = $ocrResponse->json();
            $count = $data['transactions_found'];

            if ($count === 0) {
                $this->sendMessage($waId,
                    "❌ Sikupata miamala ya M-Pesa kwenye picha hii.\n\n" .
                    "Vidokezo:\n" .
                    "• Hakikisha picha ina maandishi wazi\n" .
                    "• Jaribu screenshot ya SMS moja kwa moja\n" .
                    "• Au andika SMS kwa maandishi moja kwa moja"
                );
                return;
            }

            // Save transactions
            $saved = 0;
            foreach ($data['transactions'] as $tx) {
                if (!empty($tx['mpesa_reference'])) {
                    $exists = $business->transactions()
                        ->where('mpesa_reference', $tx['mpesa_reference'])
                        ->exists();
                    if ($exists) continue;
                }

                $business->transactions()->create([
                    'type'               => $tx['type'],
                    'amount'             => $tx['amount'],
                    'counterparty_name'  => $tx['counterparty_name'] ?? 'Unknown',
                    'counterparty_phone' => $tx['counterparty_phone'] ?? null,
                    'mpesa_reference'    => $tx['mpesa_reference'] ?? null,
                    'transacted_at'      => $tx['transacted_at'],
                    'raw_sms'            => 'WhatsApp OCR',
                ]);
                $saved++;
            }

            $this->sendMessage($waId,
                "✅ *Nimepata miamala {$count}!*\n" .
                "💾 Imehifadhiwa: {$saved} mpya\n\n" .
                "Andika *alama* kupata alama yako ya mkopo iliyosasishwa."
            );

        } catch (\Exception $e) {
            $this->sendMessage($waId, "Hitilafu imetokea. Tafadhali jaribu tena au wasiliana nasi.");
        }
    }

    // ── Helper methods ─────────────────────────────────────────────────────────

    private function sendWelcome(string $waId, ?User $user): void
    {
        if ($user) {
            $business = $user->businesses()->first();
            $score    = $business?->latestCreditScore;
            $greeting = $score
                ? "Karibu tena, *{$user->name}*! 👋\n\nAlama yako ya sasa: *{$score->score}/1000* (Daraja {$score->grade})"
                : "Karibu tena, *{$user->name}*! 👋\n\nBado huna alama ya mkopo.";

            $this->sendMessage($waId, $greeting . "\n\nAndika *msaada* kuona chaguo zote.");
        } else {
            $this->sendMessage($waId,
                "🌟 *Karibu Kitu Analytics!*\n\n" .
                "Tunakusaidia kupata mkopo kwa kutumia historia yako ya M-Pesa.\n\n" .
                "Andika *sajili* kuanza au *msaada* kwa maelezo zaidi."
            );
        }
    }

    private function sendMenu(string $waId, ?User $user): void
    {
        $menu = "📋 *Menyu ya Kitu Analytics*\n\n";
        if ($user) {
            $menu .= "1️⃣ *alama* — Angalia alama ya mkopo\n";
            $menu .= "2️⃣ *fedha* — Mwenendo wa fedha\n";
            $menu .= "📸 *Tuma picha* — Pakia SMS za M-Pesa\n\n";
        } else {
            $menu .= "5️⃣ *sajili* — Jisajili kupata alama\n\n";
        }
        $menu .= "🌐 App: kitu.co.tz\n";
        $menu .= "📞 USSD: *384*8562#";

        $this->sendMessage($waId, $menu);
    }

    private function sendScore(string $waId, string $phone, ?User $user): void
    {
        if (!$user) {
            $this->sendMessage($waId, "Hujasajiliwa bado. Andika *sajili* kuanza.");
            return;
        }

        $business = $user->businesses()->first();
        $score    = $business?->latestCreditScore;

        if (!$score) {
            $this->sendMessage($waId,
                "Bado huna alama ya mkopo.\n\n" .
                "Tuma picha ya SMS zako za M-Pesa tupate alama yako ya kwanza! 📸"
            );
            return;
        }

        $emoji = match(true) {
            $score->score >= 800 => '🟢',
            $score->score >= 650 => '🟡',
            $score->score >= 500 => '🟠',
            default              => '🔴',
        };

        $recommendation = match(true) {
            $score->score >= 800 => '✅ Unastahili mkopo mkubwa',
            $score->score >= 650 => '✅ Unastahili mkopo',
            $score->score >= 500 => '⚠️ Unaweza kuomba mkopo mdogo',
            default              => '❌ Boresha biashara yako kwanza',
        };

        $this->sendMessage($waId,
            "{$emoji} *Alama yako ya Mkopo*\n\n" .
            "📊 Alama: *{$score->score}/1000*\n" .
            "🏆 Daraja: *{$score->grade}*\n" .
            "💰 Uwezekano wa kulipa: *" . number_format((float)$score->repayment_likelihood, 1) . "%*\n" .
            "📅 Tarehe: *{$score->calculated_at->format('d M Y')}*\n\n" .
            "{$recommendation}\n\n" .
            "Tuma picha mpya ya M-Pesa kuboresha alama yako! 📸"
        );
    }

    private function sendCashFlow(string $waId, string $phone, ?User $user): void
    {
        if (!$user) {
            $this->sendMessage($waId, "Hujasajiliwa. Andika *sajili*.");
            return;
        }

        $business       = $user->businesses()->first();
        $totalIncoming  = $business?->transactions()->where('type', 'incoming')->sum('amount') ?? 0;
        $totalOutgoing  = $business?->transactions()->whereIn('type', ['outgoing', 'withdrawal'])->sum('amount') ?? 0;
        $net            = $totalIncoming - $totalOutgoing;
        $txCount        = $business?->transactions()->count() ?? 0;

        $netEmoji = $net >= 0 ? '📈' : '📉';

        $this->sendMessage($waId,
            "💹 *Mwenendo wa Fedha — {$business->name}*\n\n" .
            "💚 Zinazoingia: *TZS " . number_format($totalIncoming) . "*\n" .
            "🔴 Zinazotoka: *TZS " . number_format($totalOutgoing) . "*\n" .
            "{$netEmoji} Nafasi: *TZS " . number_format($net) . "*\n\n" .
            "📋 Miamala yote: *{$txCount}*\n\n" .
            "Tuma picha ya SMS za M-Pesa kuongeza miamala zaidi! 📸"
        );
    }

    private function sendMessage(string $to, string $message): void
    {
        if (empty($this->accessToken) || empty($this->phoneNumberId)) {
            \Log::info("WhatsApp [SANDBOX]: To {$to}: {$message}");
            return;
        }

        Http::withToken($this->accessToken)
            ->post("https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => $message],
            ]);
    }

    private function getMediaUrl(string $mediaId): ?string
    {
        $response = Http::withToken($this->accessToken)
            ->get("https://graph.facebook.com/v18.0/{$mediaId}");

        return $response->json('url');
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-]/', '', $phone);
        if (str_starts_with($phone, '+255')) return '0' . substr($phone, 4);
        if (str_starts_with($phone, '255'))  return '0' . substr($phone, 3);
        return $phone;
    }
}