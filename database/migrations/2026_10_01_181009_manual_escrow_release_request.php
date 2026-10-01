<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{if(Schema::hasTable('escrow_transactions'))Schema::table('escrow_transactions',function(Blueprint $table){$table->timestamp('release_requested_at')->nullable();$table->foreignId('release_requested_by_customer_id')->nullable()->constrained('customers')->nullOnDelete();});}
 public function down():void{}
};