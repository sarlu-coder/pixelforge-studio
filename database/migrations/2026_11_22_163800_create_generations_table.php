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
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pixelforge_id')->nullable()->unique(); // gen_...
            $table->string('idempotency_key')->unique();
            $table->text('prompt');
            $table->string('size');    // 1024x1024, 1536x1024, 1024x1536
            $table->string('quality'); // low, medium, high
            $table->json('reference_urls')->nullable();

            // Lifecycle: queued -> processing -> succeeded | failed
            $table->string('status')->default('queued');
            $table->text('image_url')->nullable();
            $table->text('error_message')->nullable();

            // Exact decimal strings in USD (never floats) — includes 25% user markup
            $table->decimal('provider_cost', 16, 8)->nullable();
            $table->decimal('estimated_cost', 16, 8)->default('0.00000000');
            $table->decimal('actual_cost', 16, 8)->nullable();

            $table->timestamp('provider_updated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
