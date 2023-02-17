<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('user_id');
            $table->foreignId('rate_group_id')->nullable();
            // $table->string('group')->default('No group');
            $table->string('name')->unique()->nullable();
            $table->integer('year')->nullable();
                $table->integer('year_low')->nullable();
                $table->integer('year_high')->nullable();
            $table->integer('rate')->nullable();
                // $table->integer('rate_low')->nullable();
                // $table->integer('rate_high')->nullable();
            $table->integer('term')->nullable();
                // $table->integer('term_low')->nullable();
                // $table->integer('term_high')->nullable();
            $table->json('columns')->nullable();
            $table->timestamps();

            // Foreign constraints
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('rate_group_id')->references('id')->on('rate_groups');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rates');
    }
};
