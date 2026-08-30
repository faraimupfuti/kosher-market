<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->dropIndex(['seller_id', 'status']);
            $table->dropIndex(['seller_id']);
            $table->unsignedBigInteger('vendor_id')->nullable()->after('buyer_id');
            $table->index(['vendor_id', 'status']);
        });

        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->dropColumn('seller_id');
        });

        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->string('bitcoin_payment_address')->nullable();
            $table->string('bitcoin_txid')->nullable()->index();
            $table->decimal('bitcoin_amount', 20, 8)->nullable();
            $table->unsignedInteger('bitcoin_confirmations')->default(0);
            $table->timestamp('payment_detected_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->index(['status', 'payment_confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->dropIndex(['status', 'payment_confirmed_at']);
            $table->dropIndex(['bitcoin_txid']);
            $table->dropColumn([
                'bitcoin_payment_address', 'bitcoin_txid', 'bitcoin_amount',
                'bitcoin_confirmations', 'payment_detected_at', 'payment_confirmed_at',
            ]);
            $table->unsignedBigInteger('seller_id')->nullable()->index();
            $table->index(['seller_id', 'status']);
            $table->dropIndex(['vendor_id', 'status']);
            $table->dropColumn('vendor_id');
        });
    }
};
