<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('escrow_transactions')) {
            Schema::table('escrow_transactions', function (Blueprint $table) {
                $table->decimal('xmr_amount', 30, 12)->nullable()->change();
            });
        }
    }

    public function down(): void {}
};