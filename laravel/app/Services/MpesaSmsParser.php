<?php

namespace App\Services;

class MpesaSmsParser
{
    public function parse(string $sms): ?array
    {
        $sms = trim($sms);

        // Incoming: "You have received TZS 15,000 from JOHN STORE 0712345678"
        if (preg_match('/received\s+TZS\s+([\d,]+)\s+from\s+(.+?)(?:\s+(\d{10,}))?(?:\s+on\s+|$)/i', $sms, $m)) {
            return [
                'type' => 'incoming',
                'amount' => (float) str_replace(',', '', $m[1]),
                'counterparty_name' => trim($m[2]),
                'counterparty_phone' => $m[3] ?? null,
                'transacted_at' => $this->extractDate($sms) ?? now(),
                'mpesa_reference' => $this->extractReference($sms),
            ];
        }

        // Outgoing: "Confirmed. You have sent TZS 15,000 to JOHN STORE"
        if (preg_match('/sent\s+TZS\s+([\d,]+)\s+to\s+(.+?)(?:\s+(\d{10,}))?(?:\s+on\s+|\.|\s*$)/i', $sms, $m)) {
            return [
                'type' => 'outgoing',
                'amount' => (float) str_replace(',', '', $m[1]),
                'counterparty_name' => trim($m[2]),
                'counterparty_phone' => $m[3] ?? null,
                'transacted_at' => $this->extractDate($sms) ?? now(),
                'mpesa_reference' => $this->extractReference($sms),
            ];
        }

        // Withdrawal: "You have withdrawn TZS 10,000"
        if (preg_match('/withdrawn\s+TZS\s+([\d,]+)/i', $sms, $m)) {
            return [
                'type' => 'withdrawal',
                'amount' => (float) str_replace(',', '', $m[1]),
                'counterparty_name' => 'ATM/Agent',
                'transacted_at' => $this->extractDate($sms) ?? now(),
                'mpesa_reference' => $this->extractReference($sms),
            ];
        }

        return null;
    }

    private function extractDate(string $sms): ?string
    {
        if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{2,4})\s+at\s+(\d{1,2}:\d{2}\s*[AP]M)/i', $sms, $m)) {
            return date('Y-m-d H:i:s', strtotime("{$m[1]} {$m[2]}"));
        }
        return null;
    }

    private function extractReference(string $sms): ?string
    {
        if (preg_match('/\b([A-Z0-9]{10,12})\b/', $sms, $m)) {
            return $m[1];
        }
        return null;
    }
}