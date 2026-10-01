<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{if(Schema::hasTable('vendor_wallets'))Schema::table('vendor_wallets',function(Blueprint $table){$table->string('crypto',10)->default('XMR')->change();});}
 public function down():void{}
};