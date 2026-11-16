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
            $table->uuid('id')->primary();
            // Deleting an account must not erase what Pixelforge billed us for it.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_id')->nullable()->unique();
            $table->string('status');
            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->json('reference_images');
            $table->unsignedBigInteger('estimated_price_nanos');
            $table->unsignedBigInteger('max_price_nanos');
            $table->json('rates')->nullable();
            $table->unsignedInteger('input_text_tokens')->nullable();
            $table->unsignedInteger('input_image_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->unsignedBigInteger('provider_cost_nanos')->nullable();
            $table->unsignedBigInteger('price_nanos')->nullable();
            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'completed_at']);
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
