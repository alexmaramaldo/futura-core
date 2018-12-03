<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBuyRentTable
{
    public function up()
    {
        Schema::create('buy_rent', function(Blueprint $table){
            $table->increments('id');
            $table->integer('user_id');
//            $table->enum('item_type', ['show', 'movie', 'season', 'video']);
            $table->enum('payment_method', ['apple_pay','bank_slip','credit_card','gift_card']);
            $table->enum('rental_type', ['buy', 'rent']);
            $table->string('apple_pay_receipt')->nullable();
            $table->datetime('expiration_date')->nullable();
            $table->datetime('expire_at')->nullable();
            $table->datetime('start_at')->nullable();
            $table->timestamps();
        });

        Schema::create('buy_rent_items', function(Blueprint $table){
            $table->increments('id');
            $table->integer('item_id');
            $table->enum('item_type', ['show', 'movie', 'season', 'video']);
            $table->decimal('price',10,2);
            $table->integer('buy_rent_id');
            $table->timestamps();
        });

    }

    public function down()
    {
        Schema::drop('buy_rent');
        Schema::drop('buy_rent_items');
    }
}