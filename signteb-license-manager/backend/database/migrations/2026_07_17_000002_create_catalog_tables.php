<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('key_prefix', 5)->unique();
            $table->string('category', 64)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'hidden', 'retired'])->default('active');
            $table->text('signing_secret'); // encrypted cast — HMAC key for API response signing
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->enum('channel', ['stable', 'beta'])->default('stable');
            $table->text('release_notes')->nullable();
            $table->string('min_php', 10)->nullable();
            $table->string('min_wp', 10)->nullable();
            $table->string('artifact_path')->nullable();
            $table->char('artifact_sha256', 64)->nullable();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'version']);
            $table->index(['product_id', 'channel', 'released_at']);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->enum('tier', ['starter', 'professional', 'clinic', 'agency', 'enterprise']);
            $table->enum('billing_cycle', ['monthly', 'yearly', 'lifetime']);
            $table->decimal('price', 12, 2);
            $table->char('currency', 3)->default('IRR');
            $table->unsignedSmallInteger('activation_limit')->default(1);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedSmallInteger('grace_days')->default(14);
            $table->unsignedBigInteger('monthly_token_limit')->nullable(); // AI quota; NULL = no AI access
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'slug', 'billing_cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
        Schema::dropIfExists('product_versions');
        Schema::dropIfExists('products');
    }
};
