<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->foreign('buyer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->dropForeign(['buyer_id']);
        });
    }
};
