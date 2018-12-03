<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateApplePayTransactions
{
    public function up()
    {
        Schema::create('apple_pay_transactions', function(Blueprint $table){
            $table->increments('id');
            $table->string('receipt');
            $table->integer('transaction_id');
            $table->integer('in_app_product_id');
            $table->integer('buy_rent_id');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('apple_pay_transactions');
    }
}