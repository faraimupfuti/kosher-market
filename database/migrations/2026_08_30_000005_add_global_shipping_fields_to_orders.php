<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_rate_id')
                ->nullable()
                ->constrained('vendor_shipping_rates')
                ->nullOnDelete();
            $table->string('shipping_country', 2)->nullable()->index();
            $table->string('shipping_full_name', 160)->nullable();
            $table->string('shipping_address_line1', 255)->nullable();
            $table->string('shipping_address_line2', 255)->nullable();
            $table->string('shipping_city', 120)->nullable();
            $table->string('shipping_state', 120)->nullable();
            $table->string('shipping_postal_code', 40)->nullable();
            $table->decimal('shipping_cost_btc', 20, 8)->default(0);
            $table->unsignedSmallInteger('estimated_delivery_min_days')->nullable();
            $table->unsignedSmallInteger('estimated_delivery_max_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['shipping_rate_id']);
            $table->dropColumn([
                'shipping_rate_id',
                'shipping_country',
                'shipping_full_name',
                'shipping_address_line1',
                'shipping_address_line2',
                'shipping_city',
                'shipping_state',
                'shipping_postal_code',
                'shipping_cost_btc',
                'estimated_delivery_min_days',
                'estimated_delivery_max_days',
            ]);
        });
    }
};
