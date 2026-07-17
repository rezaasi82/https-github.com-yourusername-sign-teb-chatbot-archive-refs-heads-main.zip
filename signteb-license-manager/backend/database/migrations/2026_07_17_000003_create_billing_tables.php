<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->enum('status', ['trialing', 'active', 'past_due', 'grace', 'cancelled', 'expired'])->default('trialing');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->string('gateway', 20)->nullable();
            $table->string('gateway_ref')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'current_period_end']); // renewal scheduler scan
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('number', 20)->unique(); // SLM-2026-000001
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'open', 'paid', 'void', 'refunded'])->default('draft');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->char('currency', 3);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->json('line_items');
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->enum('gateway', ['zarinpal', 'nextpay', 'stripe', 'manual', 'bank_transfer']);
            $table->string('gateway_ref')->nullable();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded'])->default('pending');
            $table->string('failure_reason')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'gateway_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
    }
};
