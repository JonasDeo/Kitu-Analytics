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
        Schema::create('revenue_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lender_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // api_query, lead_purchase, subscription, commission
            $table->string('reference')->unique(); // internal billing ref
            $table->decimal('amount_tzs', 15, 2);
            $table->decimal('amount_usd', 10, 4)->nullable();
            $table->string('currency')->default('TZS');
            $table->enum('status', ['pending', 'billed', 'paid', 'disputed'])->default('pending');
            $table->json('metadata')->nullable(); // query type, business_id queried etc
            $table->timestamp('billed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_events');
    }
};
