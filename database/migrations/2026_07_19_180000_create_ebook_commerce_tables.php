<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebooks', function (Blueprint $table) {
            $table->id();
            $table->string('volume_slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_cents')->nullable();
            $table->string('currency', 3)->default('BRL');
            $table->string('version')->default('1.0');
            $table->string('file_path')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ebook_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ebook_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('status')->default('pending')->index();
            $table->string('payment_provider')->nullable();
            $table->string('external_reference')->nullable()->index();
            $table->string('payment_id')->nullable()->unique();
            $table->uuid('idempotency_key')->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });

        Schema::create('ebook_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ebook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ebook_order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('granted_at');
            $table->timestamps();

            $table->unique(['user_id', 'ebook_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebook_entitlements');
        Schema::dropIfExists('ebook_orders');
        Schema::dropIfExists('ebooks');
    }
};
