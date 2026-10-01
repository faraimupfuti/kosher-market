<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // This migration is intentionally fail-safe: it refuses to reinterpret
        // existing BTC financial data as XMR.
        $financialTables = [
            'orders' => ['currency', 'shipping_cost_btc'],
            'escrow_transactions' => ['currency', 'bitcoin_payment_address', 'bitcoin_txid', 'bitcoin_amount'],
            'vendor_wallets' => ['crypto', 'deposit_address'],
            'bitcoin_settlements' => ['currency', 'bitcoin_txid'],
        ];

        $hasLegacyData = false;
        if (Schema::hasTable('orders')) $hasLegacyData = $hasLegacyData || DB::table('orders')->where('currency','BTC')->exists();
        if (Schema::hasTable('escrow_transactions')) $hasLegacyData = $hasLegacyData || DB::table('escrow_transactions')->where('currency','BTC')->exists();
        if (Schema::hasTable('vendor_wallets')) $hasLegacyData = $hasLegacyData || DB::table('vendor_wallets')->where('crypto','BTC')->exists();
        if (Schema::hasTable('bitcoin_settlements')) $hasLegacyData = $hasLegacyData || DB::table('bitcoin_settlements')->where('currency','BTC')->exists();
        if ($hasLegacyData) {
            throw new RuntimeException('Monero-first migration refused: existing BTC financial records require an explicit migration plan before changing the marketplace currency.');
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function(Blueprint $table) {
                if (!Schema::hasColumn('orders','shipping_cost_xmr')) $table->decimal('shipping_cost_xmr',20,12)->nullable()->after('shipping_postal_code');
            });
        }
        if (Schema::hasTable('escrow_transactions')) {
            Schema::table('escrow_transactions', function(Blueprint $table) {
                if (!Schema::hasColumn('escrow_transactions','xmr_payment_address')) $table->string('xmr_payment_address',180)->nullable();
                if (!Schema::hasColumn('escrow_transactions','xmr_txid')) $table->string('xmr_txid',128)->nullable()->index();
                if (!Schema::hasColumn('escrow_transactions','xmr_amount')) $table->decimal('xmr_amount',20,12)->nullable();
                if (!Schema::hasColumn('escrow_transactions','xmr_confirmations')) $table->unsignedInteger('xmr_confirmations')->default(0);
            });
        }
        if (Schema::hasTable('vendor_wallets')) {
            Schema::table('vendor_wallets', function(Blueprint $table) {
                if (!Schema::hasColumn('vendor_wallets','xmr_atomic_available')) $table->decimal('xmr_atomic_available',30,0)->default('0');
                if (!Schema::hasColumn('vendor_wallets','xmr_atomic_locked')) $table->decimal('xmr_atomic_locked',30,0)->default('0');
                if (!Schema::hasColumn('vendor_wallets','xmr_deposit_address')) $table->string('xmr_deposit_address',180)->nullable()->unique();
            });
        }
        if (Schema::hasTable('bitcoin_settlements')) {
            Schema::table('bitcoin_settlements', function(Blueprint $table) {
                if (!Schema::hasColumn('bitcoin_settlements','xmr_txid')) $table->string('xmr_txid',128)->nullable()->index();
            });
        }
        if (Schema::hasTable('vendors')) {
            Schema::table('vendors', function(Blueprint $table) {
                if (!Schema::hasColumn('vendors','xmr_payout_address')) $table->string('xmr_payout_address',180)->nullable();
                if (!Schema::hasColumn('vendors','xmr_payout_address_verified_at')) $table->timestamp('xmr_payout_address_verified_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Financial columns are retained deliberately to avoid destructive rollback.
    }
};
