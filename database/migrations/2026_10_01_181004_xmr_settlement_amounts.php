<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('bitcoin_settlements')) {
            Schema::table('bitcoin_settlements', function (Blueprint $table) {
                $table->decimal('xmr_amount',30,12)->nullable();
            });
        }
        if (Schema::hasTable('vendor_registration_fees')) {
            Schema::table('vendor_registration_fees', function (Blueprint $table) {
                $table->decimal('xmr_amount',30,12)->nullable();
            });
        }
    }
    public function down(): void {}
};