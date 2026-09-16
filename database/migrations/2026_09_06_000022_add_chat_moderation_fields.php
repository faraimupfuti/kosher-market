<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('blocked_by_customer_at')->nullable()->after('last_message_at');
            $table->timestamp('blocked_by_vendor_at')->nullable()->after('blocked_by_customer_at');
        });

        Schema::create('chat_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('reporter_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('reason', 100);
            $table->text('details')->nullable();
            $table->string('status', 30)->default('open');
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reports');
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['blocked_by_customer_at', 'blocked_by_vendor_at']);
        });
    }
};
