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
        Schema::create('users', function (Blueprint $table) {
            $table->id()->autoIncrement();
            $table->char('telegram_id', 100)->nullable();
            $table->char('username', 100)->nullable();
            $table->char('fullname', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->char('office_number', 100)->nullable();
            $table->char('work_status', 100)->nullable();
            $table->boolean('is_admin')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
