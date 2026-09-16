<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_registration_fees', function (Blueprint $table) {
            $table->text('checkout_url')->nullable()->after('btcpay_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_registration_fees', function (Blueprint $table) {
            $table->dropColumn('checkout_url');
        });
    }
};
