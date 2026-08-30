<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->unique();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('vendor_shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('vendor_shipping_zone_countries', function (Blueprint $table) {
            $table->foreignId('shipping_zone_id')->constrained('vendor_shipping_zones')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->primary(['shipping_zone_id', 'country_id']);
        });

        Schema::create('vendor_shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained('vendor_shipping_zones')->cascadeOnDelete();
            $table->string('service_name');
            $table->decimal('price_btc', 20, 8)->default(0);
            $table->decimal('free_shipping_threshold_btc', 20, 8)->nullable();
            $table->unsignedInteger('min_delivery_days')->nullable();
            $table->unsignedInteger('max_delivery_days')->nullable();
            $table->string('tracking_url_template')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('product_shipping_countries', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->primary(['product_id', 'country_id']);
        });

        // The existing schema stores the detailed destination in shipping_addresses.
        // Keep the order-level shipping metadata independent of that table so this
        // migration does not depend on a non-existent orders.shipping_address column.
        Schema::table('orders', function (Blueprint $table) {
            $table->char('shipping_country_code', 2)->nullable();
            $table->decimal('shipping_cost_btc', 20, 8)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shipping_country_code')) {
                $table->dropColumn('shipping_country_code');
            }
            if (Schema::hasColumn('orders', 'shipping_cost_btc')) {
                $table->dropColumn('shipping_cost_btc');
            }
        });
        Schema::dropIfExists('product_shipping_countries');
        Schema::dropIfExists('vendor_shipping_rates');
        Schema::dropIfExists('vendor_shipping_zone_countries');
        Schema::dropIfExists('vendor_shipping_zones');
        Schema::dropIfExists('countries');
    }
};
