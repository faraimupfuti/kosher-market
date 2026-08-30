<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('btcpay_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_id', 191)->nullable()->unique();
            $table->string('event_fingerprint', 64)->unique();
            $table->string('event_type', 100)->nullable()->index();
            $table->string('invoice_id', 191)->nullable()->index();
            $table->string('payout_id', 191)->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('btcpay_webhook_events');
    }
};
