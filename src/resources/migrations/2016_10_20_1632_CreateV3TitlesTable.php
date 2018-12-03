<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateV3TitlesTable
{
    public function up()
    {
        Schema::create('v3_titles', function(Blueprint $table){
            $table->increments('id');
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->string('cover')->nullable();
            $table->enum('type', ['show', 'movie'])->default('show');
            $table->boolean('status')->default(false)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('v3_titles');
    }
}