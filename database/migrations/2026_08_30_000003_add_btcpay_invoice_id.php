<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->string('btcpay_invoice_id')->nullable()->unique()->after('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->dropUnique(['btcpay_invoice_id']);
            $table->dropColumn('btcpay_invoice_id');
        });
    }
};
