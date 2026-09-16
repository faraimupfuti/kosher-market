<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_registration_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained('vendors')->cascadeOnDelete();
            $table->string('currency', 3)->default('BTC');
            $table->decimal('usd_amount', 12, 2)->default(200.00);
            $table->decimal('btc_amount', 18, 8)->nullable();
            $table->unsignedBigInteger('btc_satoshis')->nullable();
            $table->string('status')->default('pending');
            $table->string('btcpay_invoice_id')->nullable()->unique();
            $table->string('bitcoin_txid')->nullable()->index();
            $table->string('bitcoin_payment_address')->nullable();
            $table->unsignedInteger('bitcoin_confirmations')->default(0);
            $table->timestamp('payment_detected_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_registration_fees');
    }
};
