<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('pseudonym', 40)->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->string('phone', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropUnique(['pseudonym']);
            $table->dropColumn('pseudonym');
            $table->string('email')->nullable(false)->change();
        });
    }
};
