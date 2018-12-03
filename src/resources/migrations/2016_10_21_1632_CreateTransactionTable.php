<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionTable
{
    public function up()
    {
        Schema::create('transactions', function(Blueprint $table){
            $table->increments('id');
            $table->integer('user_id');
            $table->enum('payment_method', ['credit_card', 'bank_slip', 'apple_pay', 'gift_card']);
            $table->decimal('value', 10, 2);
            $table->string('status')->default('inactive');
            $table->integer('id_transacao_vindi')->nullable();
            $table->integer('buy_rent_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('transaction');
    }
}
