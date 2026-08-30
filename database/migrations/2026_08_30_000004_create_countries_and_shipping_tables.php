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

        Schema::table('orders', function (Blueprint $table) {
            $table->char('shipping_country_code', 2)->nullable()->after('shipping_address');
            $table->decimal('shipping_cost_btc', 20, 8)->default(0)->after('shipping_country_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_country_code', 'shipping_cost_btc']);
        });
        Schema::dropIfExists('product_shipping_countries');
        Schema::dropIfExists('vendor_shipping_rates');
        Schema::dropIfExists('vendor_shipping_zone_countries');
        Schema::dropIfExists('vendor_shipping_zones');
        Schema::dropIfExists('countries');
    }
};
