<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::find(1); // Jasiri General Store

        if (!$business) {
            $this->command->error('Business ID 1 not found. Create it first.');
            return;
        }

        // Clear existing transactions for a clean seed
        $business->transactions()->delete();

        $customers = [
            ['name' => 'JOHN MWAKAJE', 'phone' => '0789123456'],
            ['name' => 'MARY KIMARO', 'phone' => '0765432109'],
            ['name' => 'PETER NDOSI', 'phone' => '0712998877'],
            ['name' => 'GRACE MWANGA', 'phone' => '0754112233'],
            ['name' => 'SALEH JUMA', 'phone' => '0688445566'],
        ];

        $suppliers = [
            ['name' => 'KILIMANJARO WHOLESALERS', 'phone' => '0732004455'],
            ['name' => 'MOSHI FRESH PRODUCE', 'phone' => '0786551122'],
        ];

        $balance = 500000; // starting balance TZS
        $startDate = Carbon::now()->subDays(90);

        // Simulate 90 days of realistic daily business activity
        for ($day = 0; $day < 90; $day++) {
            $currentDate = $startDate->copy()->addDays($day);

            // Skip some days entirely (not every day has activity - realistic)
            if (rand(1, 10) <= 2) {
                continue;
            }

            // 1-4 incoming customer payments per active day
            $salesCount = rand(1, 4);
            for ($i = 0; $i < $salesCount; $i++) {
                $customer = $customers[array_rand($customers)];
                $amount = $this->realisticSaleAmount($currentDate);
                $balance += $amount;

                Transaction::create([
                    'business_id' => $business->id,
                    'type' => 'incoming',
                    'amount' => $amount,
                    'counterparty_name' => $customer['name'],
                    'counterparty_phone' => $customer['phone'],
                    'balance_after' => $balance,
                    'transacted_at' => $currentDate->copy()->addHours(rand(8, 19))->addMinutes(rand(0, 59)),
                    'mpesa_reference' => strtoupper(substr(md5(uniqid()), 0, 10)),
                ]);
            }

            // Restocking from suppliers - roughly weekly
            if ($day % 7 === 0) {
                $supplier = $suppliers[array_rand($suppliers)];
                $amount = rand(80000, 200000);
                $balance -= $amount;

                Transaction::create([
                    'business_id' => $business->id,
                    'type' => 'outgoing',
                    'amount' => $amount,
                    'counterparty_name' => $supplier['name'],
                    'counterparty_phone' => $supplier['phone'],
                    'balance_after' => $balance,
                    'transacted_at' => $currentDate->copy()->addHours(rand(8, 11)),
                    'mpesa_reference' => strtoupper(substr(md5(uniqid()), 0, 10)),
                ]);
            }

            // Occasional cash withdrawal for personal/operational use
            if (rand(1, 10) <= 2) {
                $amount = rand(20000, 60000);
                $balance -= $amount;

                Transaction::create([
                    'business_id' => $business->id,
                    'type' => 'withdrawal',
                    'amount' => $amount,
                    'counterparty_name' => 'ATM/Agent',
                    'balance_after' => $balance,
                    'transacted_at' => $currentDate->copy()->addHours(rand(12, 18)),
                    'mpesa_reference' => strtoupper(substr(md5(uniqid()), 0, 10)),
                ]);
            }
        }

        $this->command->info('Seeded ' . $business->transactions()->count() . ' transactions for ' . $business->name);
    }

    /**
     * Simulate realistic sale amounts with end-of-month salary-day spikes
     * and weekend boosts - mirrors real Tanzanian SME cash flow patterns.
     */
    private function realisticSaleAmount(Carbon $date): float
    {
        $base = rand(8000, 35000);

        // Salary day spike (around 25th-30th of month)
        if ($date->day >= 25) {
            $base *= 1.6;
        }

        // Weekend boost
        if ($date->isWeekend()) {
            $base *= 1.3;
        }

        return round($base, 2);
    }
}