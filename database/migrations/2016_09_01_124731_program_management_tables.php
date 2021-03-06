<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ProgramManagementTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //  Fields
        Schema::create('field_sets', function(Blueprint $table)
        {
            $table->increments('id')->unsigned();
            $table->string('title');
            $table->string('description', 100)->nullable();
            $table->smallInteger('order')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        //  Fields
    		Schema::create('fields', function(Blueprint $table)
    		{
      			$table->increments('id')->unsigned();
      			$table->string('uid');
      			$table->string('title');
      			$table->smallInteger('order')->nullable();
            $table->smallInteger('tag');
          	$table->integer('field_set_id')->unsigned();
            $table->softDeletes();
      			$table->timestamps();

            $table->foreign('field_set_id')->references('id')->on('field_sets');
    		});
        //  Options
    		Schema::create('options', function(Blueprint $table)
    		{
      			$table->increments('id')->unsigned();
      			$table->string('title');
      			$table->string('description', 100)->nullable();
            $table->softDeletes();
      			$table->timestamps();
    		});
        //  Field-Options
    		Schema::create('field_options', function(Blueprint $table)
    		{
            $table->increments('id')->unsigned();
      			$table->integer('field_id')->unsigned();
      			$table->integer('option_id')->unsigned();

            $table->foreign('field_id')->references('id')->on('fields');
            $table->foreign('option_id')->references('id')->on('options');
            $table->unique(array('field_id','option_id'));
    		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //  Reverse migrations
        Schema::dropIfExists('field_options');
        Schema::dropIfExists('options');
    		Schema::dropIfExists('fields');
    		Schema::dropIfExists('field_sets');
    }
}