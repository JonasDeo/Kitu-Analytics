<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RetrainRepaymentModel extends Command
{
    protected $signature   = 'kitu:retrain';
    protected $description = 'Retrain the repayment prediction model if enough outcomes exist';

    public function handle(): void
    {
        $this->info('Checking model training status...');

        $mlUrl = env('ML_SERVICE_URL', 'http://ml:8001');

        // Check current status
        $status = Http::get("{$mlUrl}/model-status");

        if ($status->failed()) {
            $this->error('ML service unavailable.');
            return;
        }

        $data    = $status->json();
        $needed  = $data['repayment_outcomes_needed_to_train'];
        $collected = $data['repayment_outcomes_collected'];

        $this->line("Outcomes collected: {$collected}");
        $this->line("Outcomes needed: {$needed}");

        if ($needed > 0) {
            $this->warn("Not enough data to train. Need {$needed} more outcomes.");
            return;
        }

        // Train the model
        $this->info('Training repayment model...');
        $response = Http::timeout(120)->post("{$mlUrl}/train-repayment-model");

        if ($response->failed()) {
            $this->error('Training failed: ' . $response->body());
            return;
        }

        $result = $response->json();
        $this->info("✓ Model trained successfully!");
        $this->line("  Samples: {$result['training_samples']}");
        $this->line("  Accuracy: " . ($result['accuracy'] ?? 'N/A'));
        $this->line("  Trained at: {$result['trained_at']}");
    }
}