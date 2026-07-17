<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->enum('service', ['ai_chat', 'lead_score', 'summary', 'pdf_export', 'sheets_sync', 'crm_sync']);
            $table->unsignedInteger('tokens_used')->default(0);
            $table->unsignedInteger('request_count')->default(1);
            $table->char('period', 7); // '2026-07'
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['license_id', 'period', 'service']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->enum('status', ['open', 'pending', 'answered', 'closed'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'priority']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->enum('author_type', ['customer', 'admin']);
            $table->unsignedBigInteger('author_id');
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('usage_records');
    }
};
