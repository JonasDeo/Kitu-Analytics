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
        Schema::create('guarantor_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guarantor_business_id')->constrained('businesses')->onDelete('cascade');
            $table->foreignId('beneficiary_business_id')->constrained('businesses')->onDelete('cascade');
            $table->decimal('trust_score', 5, 2)->nullable(); // ML-generated
            $table->integer('shared_transactions_count')->default(0);
            $table->decimal('total_shared_volume', 15, 2)->default(0);
            $table->enum('status', ['detected', 'vouched', 'withdrawn'])->default('detected');
            $table->string('group_name')->nullable(); // chama/vikoba name
            $table->enum('group_type', ['chama', 'vikoba', 'informal', 'family'])->nullable();
            $table->timestamp('vouched_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guarantor_relationships');
    }
};
