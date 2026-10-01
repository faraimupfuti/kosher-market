<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{if(Schema::hasTable('vendor_wallet_transactions'))Schema::table('vendor_wallet_transactions',function(Blueprint $table){$table->decimal('xmr_atomic_amount',30,0)->nullable();$table->decimal('xmr_atomic_balance_after',30,0)->nullable();});}
 public function down():void{}
};