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
        Schema::create('score_explanations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_score_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('factor'); // e.g. "transaction_frequency"
            $table->string('impact'); // positive, negative, neutral
            $table->decimal('weight', 5, 2); // contribution to score
            $table->text('explanation_en'); // English plain language
            $table->text('explanation_sw'); // Swahili plain language
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_explanations');
    }
};
