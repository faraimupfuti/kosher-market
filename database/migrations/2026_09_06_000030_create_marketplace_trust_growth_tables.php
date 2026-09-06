<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vendors')) {
            Schema::table('vendors', function (Blueprint $table) {
                foreach ([
                    'verification_status' => fn ($t) => $t->string('verification_status', 30)->default('unverified'),
                    'trust_score' => fn ($t) => $t->unsignedTinyInteger('trust_score')->default(50),
                    'verified_at' => fn ($t) => $t->timestamp('verified_at')->nullable(),
                    'response_rate' => fn ($t) => $t->decimal('response_rate', 5, 2)->default(0),
                    'dispute_rate' => fn ($t) => $t->decimal('dispute_rate', 5, 2)->default(0),
                    'refund_rate' => fn ($t) => $t->decimal('refund_rate', 5, 2)->default(0),
                    'fulfillment_rate' => fn ($t) => $t->decimal('fulfillment_rate', 5, 2)->default(0),
                ] as $name => $definition) {
                    if (! Schema::hasColumn('vendors', $name)) $definition($table);
                }
            });
        }

        if (! Schema::hasTable('saved_searches')) {
            Schema::create('saved_searches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('name');
                $table->string('query')->nullable();
                $table->json('filters')->nullable();
                $table->boolean('notify_enabled')->default(true);
                $table->timestamps();
                $table->index(['customer_id', 'notify_enabled']);
            });
        }

        if (! Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->string('code')->unique();
                $table->enum('type', ['percent', 'fixed']);
                $table->decimal('value', 20, 8);
                $table->decimal('minimum_order_btc', 20, 8)->default(0);
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->index(['vendor_id', 'active']);
            });
        }

        if (Schema::hasTable('coupons') && ! Schema::hasTable('coupon_usages')) {
            Schema::create('coupon_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->decimal('discount_btc', 20, 8);
                $table->timestamps();
                $table->unique(['coupon_id', 'order_id']);
            });
        }

        if (! Schema::hasTable('order_tracking_events')) {
            Schema::create('order_tracking_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->string('status', 50);
                $table->string('location')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();
                $table->index(['order_id', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('disputes')) {
            Schema::create('disputes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->string('reason', 80);
                $table->text('description');
                $table->enum('status', ['open', 'seller_response', 'mediation', 'escalated', 'resolved', 'rejected'])->default('open');
                $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');
                $table->timestamp('sla_due_at')->nullable();
                $table->string('resolution')->nullable();
                $table->text('resolution_note')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'priority', 'sla_due_at']);
                $table->index(['vendor_id', 'status']);
            });
        }

        if (Schema::hasTable('disputes') && ! Schema::hasTable('dispute_evidence')) {
            Schema::create('dispute_evidence', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime')->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_evidence');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('order_tracking_events');
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('saved_searches');
        if (Schema::hasTable('vendors')) {
            Schema::table('vendors', function (Blueprint $table) {
                foreach (['verification_status', 'trust_score', 'verified_at', 'response_rate', 'dispute_rate', 'refund_rate', 'fulfillment_rate'] as $column) {
                    if (Schema::hasColumn('vendors', $column)) $table->dropColumn($column);
                }
            });
        }
    }
};
