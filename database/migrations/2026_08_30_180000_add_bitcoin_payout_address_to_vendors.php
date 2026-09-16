<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendors') && ! Schema::hasColumn('vendors', 'bitcoin_payout_address')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('bitcoin_payout_address', 120)->nullable()->after('phone');
                $table->timestamp('bitcoin_payout_address_verified_at')->nullable()->after('bitcoin_payout_address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendors')) {
            Schema::table('vendors', function (Blueprint $table) {
                if (Schema::hasColumn('vendors', 'bitcoin_payout_address_verified_at')) {
                    $table->dropColumn('bitcoin_payout_address_verified_at');
                }
                if (Schema::hasColumn('vendors', 'bitcoin_payout_address')) {
                    $table->dropColumn('bitcoin_payout_address');
                }
            });
        }
    }
};
