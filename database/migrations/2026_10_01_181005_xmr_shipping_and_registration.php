<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vendor_shipping_rates')) {
            Schema::table('vendor_shipping_rates', function (Blueprint $table) {
                $table->decimal('price_xmr',30,12)->nullable();
                $table->decimal('free_shipping_threshold_xmr',30,12)->nullable();
            });
        }
        if (Schema::hasTable('vendor_registration_fees')) {
            Schema::table('vendor_registration_fees', function (Blueprint $table) {
                $table->decimal('xmr_satoshis_legacy',30,0)->nullable();
                $table->string('xmr_txid',128)->nullable();
                $table->string('xmr_payment_address',180)->nullable();
                $table->unsignedInteger('xmr_confirmations')->default(0);
            });
        }
    }
    public function down(): void {}
};