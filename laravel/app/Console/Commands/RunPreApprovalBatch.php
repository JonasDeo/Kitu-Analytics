<?php

namespace App\Console\Commands;

use App\Models\Lender;
use App\Models\RevenueEvent;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RunPreApprovalBatch extends Command
{
    protected $signature   = 'kitu:pre-approvals';
    protected $description = 'Run nightly pre-approval batch and notify lenders';

    public function handle(): void
    {
        $this->info('Running pre-approval batch...');

        $mlUrl   = env('ML_SERVICE_URL', 'http://ml:8001');
        $lenders = Lender::where('status', 'active')->get();
        $sms     = new SmsService();
        $total   = 0;

        foreach ($lenders as $lender) {
            $response = Http::timeout(30)->get("{$mlUrl}/pre-approvals", [
                'min_score' => $lender->min_credit_score,
                'limit'     => 50,
            ]);

            if ($response->failed()) {
                $this->warn("ML service unavailable for lender {$lender->name}");
                continue;
            }

            $leads = $response->json('leads', []);
            $count = count($leads);

            if ($count === 0) {
                $this->line("No leads for {$lender->name}");
                continue;
            }

            // Bill lender for batch
            RevenueEvent::create([
                'lender_id'  => $lender->id,
                'event_type' => 'pre_approval_batch',
                'reference'  => 'PRE-' . strtoupper(Str::random(10)),
                'amount_tzs' => $count * 2500,
                'status'     => 'billed',
                'billed_at'  => now(),
                'metadata'   => [
                    'total_leads' => $count,
                    'min_score'   => $lender->min_credit_score,
                    'batch_date'  => now()->toDateString(),
                ],
            ]);

            // Notify each qualifying SME
            foreach ($leads as $lead) {
                $sms->sendPreApprovalOffer(
                    $lead['phone'],
                    $lead['business_name'],
                    (int) $lead['recommended_max_loan_tzs'],
                    $lender->name
                );
            }

            $this->info("✓ {$lender->name}: {$count} leads, billed TZS " . number_format($count * 2500));
            $total += $count;
        }

        $this->info("Batch complete. Total leads processed: {$total}");
    }
}