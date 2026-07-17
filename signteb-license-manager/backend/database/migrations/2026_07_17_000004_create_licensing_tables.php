<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('license_key', 25)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'active', 'grace', 'expired', 'suspended', 'revoked'])->default('pending');
            $table->unsignedSmallInteger('activation_limit')->default(1);
            $table->timestamp('expires_at')->nullable(); // NULL = lifetime
            $table->timestamp('grace_ends_at')->nullable();
            $table->unsignedSmallInteger('transfers_used')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'expires_at']); // daily expiry sweep
        });

        Schema::create('activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->char('domain_hash', 64);
            $table->string('site_url')->nullable();
            $table->string('ip', 45)->nullable();
            $table->char('device_fingerprint', 64)->nullable();
            $table->enum('environment', ['production', 'staging', 'local'])->default('production');
            $table->string('sdk_version', 20)->nullable();
            $table->string('wp_version', 20)->nullable();
            $table->string('php_version', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at');
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['license_id', 'is_active']);
            $table->index(['license_id', 'domain_hash']);
            $table->index('last_seen_at');
        });

        // Immutable audit log: append-only, enforced at repository layer
        // and by MySQL grants (no UPDATE/DELETE for the app user) in production.
        Schema::create('license_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('event', 32);
            $table->enum('actor_type', ['system', 'admin', 'customer', 'api'])->default('system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['license_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_events');
        Schema::dropIfExists('activations');
        Schema::dropIfExists('licenses');
    }
};
