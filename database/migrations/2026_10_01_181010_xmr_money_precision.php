<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{foreach(['orders'=>['total_amount','unit_price','discount_amount','shipping_cost_btc'],'products'=>['price','discount_price'],'product_variants'=>['price','discount_price'],'vendor_shipping_rates'=>['price_btc','free_shipping_threshold_btc'],'bitcoin_settlements'=>['amount']] as $tableName=>$columns){if(!Schema::hasTable($tableName))continue;Schema::table($tableName,function(Blueprint $table)use($tableName,$columns){foreach($columns as $column){if(Schema::hasColumn($tableName,$column))$table->decimal($column,30,12)->change();}});}}
 public function down():void{}
};