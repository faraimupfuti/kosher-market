<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('seller_id')->nullable()->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('platform_fee', 15, 2)->default(0);
            $table->decimal('seller_amount', 15, 2);
            $table->string('currency', 10)->default('USD');
            $table->enum('status', [
                'pending', 'funded', 'processing', 'released', 'refunded', 'disputed', 'cancelled'
            ])->default('pending');
            $table->timestamp('funded_at')->nullable();
            $table->timestamp('release_due_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('release_note')->nullable();
            $table->text('refund_note')->nullable();
            $table->timestamps();
            $table->unique('order_id');
            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
        });

        Schema::create('escrow_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_transaction_id')->constrained('escrow_transactions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('reference')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('escrow_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_transaction_id')->constrained('escrow_transactions')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason');
            $table->text('description');
            $table->enum('status', ['open', 'under_review', 'resolved_buyer', 'resolved_seller', 'closed'])->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow_disputes');
        Schema::dropIfExists('escrow_ledger_entries');
        Schema::dropIfExists('escrow_transactions');
    }
};
