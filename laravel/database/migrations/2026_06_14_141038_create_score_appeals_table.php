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
        Schema::create('score_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('credit_score_id')->constrained()->onDelete('cascade');
            $table->text('reason'); // user's explanation
            $table->enum('status', ['pending', 'under_review', 'resolved', 'rejected'])
                ->default('pending');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->integer('score_before')->nullable();
            $table->integer('score_after')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('due_at')->nullable(); // 48h SLA
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_appeals');
    }
};
