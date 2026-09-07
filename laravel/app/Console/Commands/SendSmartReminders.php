<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Customer;
use App\Models\SupplierInvoice;
use App\Models\StockLevel;
use App\Services\SmsService;
use Illuminate\Console\Command;

class SendSmartReminders extends Command
{
    protected $signature   = 'kitu:reminders';
    protected $description = 'Send smart reminders for debts, bills, and low stock';

    public function handle(): void
    {
        $this->info('Sending smart reminders...');
        $sms = new SmsService();

        $debtCount     = $this->sendDebtReminders($sms);
        $supplierCount = $this->sendSupplierReminders($sms);
        $stockCount    = $this->sendLowStockAlerts($sms);

        $this->info("Done. Debt: {$debtCount}, Supplier: {$supplierCount}, Stock: {$stockCount}");
    }

    private function sendDebtReminders(SmsService $sms): int
    {
        $count = 0;

        // Customers with overdue balances (owed for more than 7 days)
        $overdueCustomers = Customer::whereHas('balance', function ($q) {
            $q->where('net_balance', '>', 0)
              ->where('last_transaction_at', '<', now()->subDays(7));
        })->with(['balance', 'business'])->get();

        foreach ($overdueCustomers as $customer) {
            if (!$customer->phone) continue;

            $balance   = $customer->balance;
            $formatted = number_format($balance->net_balance);
            $business  = $customer->business;

            $sent = $sms->sendAlert(
                $customer->phone,
                "Habari {$customer->name}, una deni la TZS {$formatted} kwa {$business->name} ambalo limekaa siku 7+. Tafadhali lipa leo. Maswali: piga *384*8562#"
            );

            if ($sent) $count++;
        }

        $this->line("  Debt reminders sent: {$count}");
        return $count;
    }

    private function sendSupplierReminders(SmsService $sms): int
    {
        $count = 0;

        // Supplier invoices due within 3 days or already overdue
        $overdueInvoices = SupplierInvoice::overdue()
            ->with(['supplier', 'business.user'])
            ->get();

        foreach ($overdueInvoices as $invoice) {
            $owner = $invoice->business?->user;
            if (!$owner?->phone) continue;

            $formatted = number_format($invoice->balance_due);
            $dueDate   = $invoice->due_date?->format('d M Y') ?? 'leo';

            $sent = $sms->sendAlert(
                $owner->phone,
                "Kumbusho: Una bili la TZS {$formatted} kwa {$invoice->supplier->name} ambalo lilipaswa kulipwa {$dueDate}. Ingia kwenye app kulipa."
            );

            if ($sent) $count++;
        }

        $this->line("  Supplier reminders sent: {$count}");
        return $count;
    }

    private function sendLowStockAlerts(SmsService $sms): int
    {
        $count = 0;

        // Products below low stock threshold
        $lowStock = StockLevel::where('quantity', '<=', \DB::raw('low_stock_threshold'))
            ->with(['product.business.user', 'branch'])
            ->get();

        foreach ($lowStock as $stock) {
            $owner = $stock->product?->business?->user;
            if (!$owner?->phone) continue;

            $qty     = number_format($stock->quantity, 0);
            $product = $stock->product->name;
            $branch  = $stock->branch->name;

            $sent = $sms->sendAlert(
                $owner->phone,
                "Onyo la stoki: {$product} imebaki {$qty} tu kwenye {$branch}. Agiza tena haraka kabla hazijaisha."
            );

            if ($sent) $count++;
        }

        $this->line("  Low stock alerts sent: {$count}");
        return $count;
    }
}