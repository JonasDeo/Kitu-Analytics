<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    private $sms;
    private bool $isSandbox;

    public function __construct()
    {
        $this->isSandbox = env('AT_ENV', 'sandbox') === 'sandbox';

        $AT = new \AfricasTalking\SDK\AfricasTalking(
            env('AT_USERNAME', 'sandbox'),
            env('AT_API_KEY', '')
        );

        $this->sms = $AT->sms();
    }

    public function sendOtp(string $phone, int $otp): bool
    {
        // Normalize phone to international format (+255XXXXXXXXX)
        $international = $this->normalizePhone($phone);

        $message = "Kitu Analytics: Nambari yako ya uthibitisho ni {$otp}. Inaisha baada ya dakika 10. Usishiriki na mtu yeyote.";

        try {
            $result = $this->sms->send([
                'to' => $international,
                'message' => $message,
                'from' => env('AT_SENDER_ID', 'KITU'),
            ]);

            Log::info('OTP SMS sent', [
                'phone' => $phone,
                'result' => $result,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('OTP SMS failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendAlert(string $phone, string $message): bool
    {
        $international = $this->normalizePhone($phone);

        try {
            $this->sms->send([
                'to' => $international,
                'message' => "Kitu Analytics: {$message}",
                'from' => env('AT_SENDER_ID', 'KITU'),
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Alert SMS failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function sendPreApprovalOffer(string $phone, string $businessName, int $loanAmount, string $lenderName): bool
    {
        $international = $this->normalizePhone($phone);
        $formatted = number_format($loanAmount);

        $message = "Habari {$businessName}! Umestahili mkopo wa TZS {$formatted} kutoka {$lenderName} kupitia Kitu Analytics. Ingia kwenye app yako kukubali. Kitu.co.tz";

        try {
            $this->sms->send([
                'to' => $international,
                'message' => $message,
                'from' => env('AT_SENDER_ID', 'KITU'),
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Pre-approval SMS failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function normalizePhone(string $phone): string
    {
        // Remove spaces and dashes
        $phone = preg_replace('/[\s\-]/', '', $phone);

        // Already international format
        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        // 255XXXXXXXXX format
        if (str_starts_with($phone, '255')) {
            return '+' . $phone;
        }

        // 07XXXXXXXX or 06XXXXXXXX → +255XXXXXXXXX
        if (str_starts_with($phone, '0')) {
            return '+255' . substr($phone, 1);
        }

        return '+255' . $phone;
    }
}