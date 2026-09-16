<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'shipping_rate_id')) {
                $table->foreignId('shipping_rate_id')
                    ->nullable()
                    ->constrained('vendor_shipping_rates')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'shipping_country')) {
                $table->string('shipping_country', 2)->nullable()->index();
            }

            if (! Schema::hasColumn('orders', 'shipping_full_name')) {
                $table->string('shipping_full_name', 160)->nullable();
            }

            if (! Schema::hasColumn('orders', 'shipping_address_line1')) {
                $table->string('shipping_address_line1', 255)->nullable();
            }

            if (! Schema::hasColumn('orders', 'shipping_address_line2')) {
                $table->string('shipping_address_line2', 255)->nullable();
            }

            if (! Schema::hasColumn('orders', 'shipping_city')) {
                $table->string('shipping_city', 120)->nullable();
            }

            if (! Schema::hasColumn('orders', 'shipping_state')) {
                $table->string('shipping_state', 120)->nullable();
            }

            if (! Schema::hasColumn('orders', 'shipping_postal_code')) {
                $table->string('shipping_postal_code', 40)->nullable();
            }

            if (! Schema::hasColumn('orders', 'shipping_cost_btc')) {
                $table->decimal('shipping_cost_btc', 20, 8)->default(0);
            }

            if (! Schema::hasColumn('orders', 'estimated_delivery_min_days')) {
                $table->unsignedSmallInteger('estimated_delivery_min_days')->nullable();
            }

            if (! Schema::hasColumn('orders', 'estimated_delivery_max_days')) {
                $table->unsignedSmallInteger('estimated_delivery_max_days')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shipping_rate_id')) {
                $table->dropForeign(['shipping_rate_id']);
            }

            $columns = [
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
            ];

            $existing = array_values(array_filter(
                $columns,
                static fn (string $column): bool => Schema::hasColumn('orders', $column)
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
