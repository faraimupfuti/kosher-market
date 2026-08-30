<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->unsignedBigInteger('buyer_id')->index();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->decimal('amount', 20, 8);
            $table->decimal('platform_fee', 20, 8)->default(0);
            $table->decimal('seller_amount', 20, 8);
            $table->string('currency', 10)->default('BTC');
            $table->string('status')->default('pending')->index();
            $table->string('btcpay_invoice_id')->nullable()->unique();
            $table->string('bitcoin_payment_address')->nullable();
            $table->string('bitcoin_txid')->nullable()->index();
            $table->decimal('bitcoin_amount', 20, 8)->nullable();
            $table->unsignedInteger('bitcoin_confirmations')->default(0);
            $table->timestamp('payment_detected_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamp('funded_at')->nullable();
            $table->timestamp('release_due_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('release_note')->nullable();
            $table->text('refund_note')->nullable();
            $table->timestamps();
            $table->unique('order_id');
            $table->index(['buyer_id', 'status']);
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('escrow_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_transaction_id')->constrained('escrow_transactions')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('type');
            $table->decimal('amount', 20, 8);
            $table->string('currency', 10)->default('BTC');
            $table->string('reference')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('escrow_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_transaction_id')->constrained('escrow_transactions')->cascadeOnDelete();
            $table->unsignedBigInteger('opened_by')->index();
            $table->string('reason');
            $table->text('description');
            $table->string('status')->default('open')->index();
            $table->unsignedBigInteger('resolved_by')->nullable()->index();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow_disputes');
        Schema::dropIfExists('escrow_ledger_entries');
        Schema::dropIfExists('escrow_transactions');
    }
};
