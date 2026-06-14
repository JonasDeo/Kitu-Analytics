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
        Schema::create('lenders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable(); // MFI, bank, SACCO
            $table->string('contact_email')->unique();
            $table->string('contact_phone')->nullable();
            $table->string('api_key')->unique();
            $table->integer('min_credit_score')->default(400);
            $table->decimal('max_loan_amount', 15, 2)->nullable();
            $table->enum('status', ['active', 'inactive', 'pending'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lenders');
    }
};
