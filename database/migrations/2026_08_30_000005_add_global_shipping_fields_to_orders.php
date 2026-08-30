<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_rate_id')->nullable()->after('shipping_method')->constrained('vendor_shipping_rates')->nullOnDelete();
            $table->string('shipping_country', 2)->nullable()->after('shipping_rate_id')->index();
            $table->string('shipping_full_name', 160)->nullable()->after('shipping_country');
            $table->string('shipping_address_line1', 255)->nullable()->after('shipping_full_name');
            $table->string('shipping_address_line2', 255)->nullable()->after('shipping_address_line1');
            $table->string('shipping_city', 120)->nullable()->after('shipping_address_line2');
            $table->string('shipping_state', 120)->nullable()->after('shipping_city');
            $table->string('shipping_postal_code', 40)->nullable()->after('shipping_state');
            $table->decimal('shipping_cost_btc', 20, 8)->default(0)->after('shipping_postal_code');
            $table->unsignedSmallInteger('estimated_delivery_min_days')->nullable()->after('shipping_cost_btc');
            $table->unsignedSmallInteger('estimated_delivery_max_days')->nullable()->after('estimated_delivery_min_days');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['shipping_rate_id']);
            $table->dropColumn([
                'shipping_rate_id','shipping_country','shipping_full_name','shipping_address_line1','shipping_address_line2',
                'shipping_city','shipping_state','shipping_postal_code','shipping_cost_btc','estimated_delivery_min_days','estimated_delivery_max_days',
            ]);
        });
    }
};
