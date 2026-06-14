<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->integer('score'); // 0 - 1000
            $table->string('grade')->nullable(); // A, B, C, D
            $table->decimal('transaction_frequency_score', 5, 2)->nullable();
            $table->decimal('cash_flow_stability_score', 5, 2)->nullable();
            $table->decimal('network_health_score', 5, 2)->nullable();
            $table->decimal('repayment_likelihood', 5, 2)->nullable();
            $table->json('factors')->nullable(); // breakdown of score factors
            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_scores');
    }
};
