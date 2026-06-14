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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('mpesa_reference')->unique()->nullable();
            $table->enum('type', ['incoming', 'outgoing', 'payment', 'withdrawal']);
            $table->decimal('amount', 15, 2);
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_phone')->nullable();
            $table->text('raw_sms')->nullable();
            $table->decimal('balance_after', 15, 2)->nullable();
            $table->timestamp('transacted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
