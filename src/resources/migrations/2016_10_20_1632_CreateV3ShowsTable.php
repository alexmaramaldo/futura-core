<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateV3ShowsTable
{
    public function up()
    {
        Schema::create('v3_shows', function(Blueprint $table){
            $table->increments('id');
            $table->string('title');
            $table->string('description');
            $table->string('cover');
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('v3_shows');
    }
}