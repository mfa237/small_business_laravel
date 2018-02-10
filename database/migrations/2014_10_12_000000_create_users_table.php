<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password', 60);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->text('address')->nullable();
            $table->string('photo')->nullable();
            $table->date('dob')->nullable();
            $table->boolean('confirmed')->default(0);
            $table->string('activation_code')->nullable();
            $table->string('confirmation_code')->nullable();
            $table->string('company')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->integer('status')->default(0); //0 - inactive 1-active 2-suspended

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('users');
    }
}
