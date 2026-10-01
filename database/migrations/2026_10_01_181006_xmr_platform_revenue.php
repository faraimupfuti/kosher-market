<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{if(Schema::hasTable('platform_revenue_entries'))Schema::table('platform_revenue_entries',function(Blueprint $table){$table->decimal('amount_xmr',30,12)->nullable();});}
 public function down():void{}
};