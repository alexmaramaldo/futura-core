<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePricingTables
{
    public function up()
    {
        Schema::create('price_range', function(Blueprint $table){
            $table->increments('id');
            $table->string('name')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2);
            $table->enum('price_type', ['buy', 'rent']);
            $table->datetime('expire_at')->nullable();
            $table->timestamps();
        });

        Schema::create('price_range_item', function (Blueprint $table){
            $table->integer('item_id');
            $table->enum('item_type', ['show', 'season', 'movie', 'video']);
            $table->integer('price_range_id')->nullable(true)->unsigned();
            $table->foreign('price_range_id')->references('id')->on('price_range');
            $table->enum('price_type', ['buy', 'rent']);
            $table->integer('pricing_expiration_id');
            $table->foreign('pricing_expiration_id')->references('id')->on('pricing_expiration');
            $table->datetime('expire_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pricing_expiration', function(Blueprint $table){
            $table->increments('id');
            $table->string('name');
            $table->integer('time_period')->nullable(true)->unsigned();
            $table->timestamps();

        });

        Schema::create('pricing_item', function(Blueprint $table){
            $table->increments('id');
            $table->integer('item_id');
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2);
            $table->enum('price_type', ['buy', 'rent']);
            $table->enum('item_type', ['show', 'season', 'movie', 'video']);
            $table->integer('pricing_expiration_id')->nullable(true)->unsigned();
            $table->foreign('pricing_expiration_id')->references('id')->on('pricing_expiration');
            $table->unique(['item_id','price_type','item_type']);
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::drop('price_range');
        Schema::drop('price_range_item');
        Schema::drop('pricing_expiration');
        Schema::drop('pricing_item');
    }
}