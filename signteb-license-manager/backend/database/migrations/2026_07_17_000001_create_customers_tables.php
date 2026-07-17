<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->enum('type', ['doctor', 'clinic', 'hospital', 'company', 'startup', 'individual'])->default('individual');
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone', 32)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->enum('locale', ['fa', 'en', 'ar'])->default('fa');
            $table->json('billing_address')->nullable();
            $table->string('tax_id', 64)->nullable();
            $table->string('password');
            $table->text('notes')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'country']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable();
            $table->string('action', 64);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('customers');
    }
};
