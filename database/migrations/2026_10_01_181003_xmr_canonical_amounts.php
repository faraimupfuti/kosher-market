<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('escrow_transactions')) {
            Schema::table('escrow_transactions', function (Blueprint $table) {
                $table->decimal('xmr_platform_fee',30,12)->nullable();
                $table->decimal('xmr_seller_amount',30,12)->nullable();
            });
        }
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('xmr_total_amount',30,12)->nullable();
                $table->decimal('xmr_unit_price',30,12)->nullable();
                $table->decimal('xmr_shipping_cost',30,12)->nullable();
            });
        }
    }
    public function down(): void {}
};