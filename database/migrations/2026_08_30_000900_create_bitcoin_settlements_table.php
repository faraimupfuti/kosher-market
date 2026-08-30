<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitcoin_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_transaction_id')->constrained('escrow_transactions')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('type', 20)->default('seller_payout');
            $table->decimal('amount', 20, 8);
            $table->string('currency', 3)->default('BTC');
            $table->string('destination_address', 120)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('btcpay_payout_id')->nullable()->unique();
            $table->string('bitcoin_txid', 100)->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['escrow_transaction_id', 'type']);
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitcoin_settlements');
    }
};
