<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateV3SeasonsTable
{
    public function up()
    {
        Schema::create('v3_seasons', function(Blueprint $table){
            $table->increments('id');
            $table->integer('title_id');
            $table->string('title');
            $table->string('description');
            $table->string('cover')->nullable();
            $table->string('highlight')->nullable();
            $table->integer('order')->nullable();
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('v3_shows');
    }
}