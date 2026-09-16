<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_revenue_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // sale_commission, vendor_onboarding, featured_listing, subscription, refund
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('escrow_transaction_id')->nullable()->constrained('escrow_transactions')->nullOnDelete();
            $table->decimal('amount_btc', 16, 8);
            $table->unsignedBigInteger('amount_satoshis');
            $table->string('reference')->unique();
            $table->string('status')->default('earned'); // pending, earned, reversed
            $table->text('description')->nullable();
            $table->timestamp('earned_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'status']);
            $table->index('earned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_revenue_entries');
    }
};
