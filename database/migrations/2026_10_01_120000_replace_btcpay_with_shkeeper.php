<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('escrow_transactions') && Schema::hasColumn('escrow_transactions', 'btcpay_invoice_id') && ! Schema::hasColumn('escrow_transactions', 'shkeeper_invoice_id')) {
            Schema::table('escrow_transactions', fn (Blueprint $table) => $table->renameColumn('btcpay_invoice_id', 'shkeeper_invoice_id'));
        }
        if (Schema::hasTable('bitcoin_settlements') && Schema::hasColumn('bitcoin_settlements', 'btcpay_payout_id') && ! Schema::hasColumn('bitcoin_settlements', 'shkeeper_payout_id')) {
            Schema::table('bitcoin_settlements', fn (Blueprint $table) => $table->renameColumn('btcpay_payout_id', 'shkeeper_payout_id'));
        }
        if (Schema::hasTable('vendor_registration_fees') && Schema::hasColumn('vendor_registration_fees', 'btcpay_invoice_id') && ! Schema::hasColumn('vendor_registration_fees', 'shkeeper_invoice_id')) {
            Schema::table('vendor_registration_fees', fn (Blueprint $table) => $table->renameColumn('btcpay_invoice_id', 'shkeeper_invoice_id'));
        }

        if (Schema::hasTable('btcpay_webhook_events') && ! Schema::hasTable('shkeeper_webhook_events')) {
            Schema::rename('btcpay_webhook_events', 'shkeeper_webhook_events');
            foreach ([['delivery_id','external_id'],['event_type','status'],['invoice_id','txid']] as [$from,$to]) {
                if (Schema::hasColumn('shkeeper_webhook_events', $from) && ! Schema::hasColumn('shkeeper_webhook_events', $to)) {
                    Schema::table('shkeeper_webhook_events', fn (Blueprint $table) => $table->renameColumn($from, $to));
                }
            }
        }

        if (! Schema::hasTable('shkeeper_webhook_events')) {
            Schema::create('shkeeper_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_fingerprint', 64)->unique();
                $table->string('external_id')->index();
                $table->string('status')->nullable();
                $table->string('txid')->nullable()->index();
                $table->timestamp('processed_at')->nullable();
                $table->text('processing_error')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shkeeper_webhook_events') && ! Schema::hasTable('btcpay_webhook_events')) {
            Schema::rename('shkeeper_webhook_events', 'btcpay_webhook_events');
        }
        if (Schema::hasTable('escrow_transactions') && Schema::hasColumn('escrow_transactions', 'shkeeper_invoice_id')) Schema::table('escrow_transactions', fn (Blueprint $table) => $table->renameColumn('shkeeper_invoice_id', 'btcpay_invoice_id'));
        if (Schema::hasTable('bitcoin_settlements') && Schema::hasColumn('bitcoin_settlements', 'shkeeper_payout_id')) Schema::table('bitcoin_settlements', fn (Blueprint $table) => $table->renameColumn('shkeeper_payout_id', 'btcpay_payout_id'));
        if (Schema::hasTable('vendor_registration_fees') && Schema::hasColumn('vendor_registration_fees', 'shkeeper_invoice_id')) Schema::table('vendor_registration_fees', fn (Blueprint $table) => $table->renameColumn('shkeeper_invoice_id', 'btcpay_invoice_id'));
    }
};
