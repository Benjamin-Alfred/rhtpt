<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClientFeedbackTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surveys', function (Blueprint $table) {
          $table->increments('id');
          $table->integer('round_id')->unsigned();
          $table->string('question');
          $table->tinyInteger('question_type')->unsigned()->default(0);
          $table->integer('created_by')->unsigned();
          $table->softDeletes();
          $table->timestamps();
          $table->foreign('round_id')->references('id')->on('rounds');
          $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('survey_responses', function (Blueprint $table) {
          $table->increments('id');
          $table->integer('pt_id')->unsigned();
          $table->integer('survey_id')->unsigned();
          $table->string('response');
          $table->integer('created_by')->unsigned();
          $table->softDeletes();
          $table->timestamps();
          $table->foreign('pt_id')->references('id')->on('pt');
          $table->foreign('survey_id')->references('id')->on('surveys');
          $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('surveys');
    }
}