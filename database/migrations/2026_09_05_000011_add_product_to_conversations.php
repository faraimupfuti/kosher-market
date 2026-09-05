<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('vendor_id')->constrained()->nullOnDelete();
            $table->index(['vendor_id', 'product_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['vendor_id', 'product_id', 'customer_id']);
            $table->dropColumn('product_id');
        });
    }
};
