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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event'); // score.generated, appeal.filed, consent.granted etc
            $table->string('auditable_type'); // polymorphic
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('model_version')->nullable(); // ML model version that acted
            $table->string('hash')->nullable(); // hash chain for immutability
            $table->timestamp('created_at'); // no updated_at — immutable
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
