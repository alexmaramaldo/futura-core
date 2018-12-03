<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGiftCardTvodTable
{
    public function up()
    {
        Schema::create('gift_card_tvod', function(Blueprint $table){
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->string('code');
            $table->integer('valid_days');
            $table->enum('item_typeitem_id', ['show', 'movie'])->nullable();
            $table->integer('item_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('gift_card_tvod');
    }
}