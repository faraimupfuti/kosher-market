<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained('vendors')->cascadeOnDelete();
            $table->string('crypto', 10)->default('BTC');
            $table->string('deposit_address', 120)->nullable()->unique();
            $table->unsignedBigInteger('available_satoshis')->default(0);
            $table->unsignedBigInteger('locked_satoshis')->default(0);
            $table->timestamps();
        });

        Schema::create('vendor_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_wallet_id')->constrained('vendor_wallets')->cascadeOnDelete();
            $table->string('type', 40);
            $table->unsignedBigInteger('amount_satoshis');
            $table->unsignedBigInteger('balance_after_satoshis');
            $table->string('reference', 120)->unique();
            $table->string('status', 30)->default('posted');
            $table->foreignId('escrow_transaction_id')->nullable()->constrained('escrow_transactions')->nullOnDelete();
            $table->foreignId('bitcoin_settlement_id')->nullable()->constrained('bitcoin_settlements')->nullOnDelete();
            $table->string('txid', 100)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_wallet_transactions');
        Schema::dropIfExists('vendor_wallets');
    }
};
