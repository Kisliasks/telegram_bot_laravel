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
    public function up(): void
    {
        Schema::create('messages_concierges', function (Blueprint $table) {
            $table->id();
            $table->foreignId("chat_id");
            $table->foreignId("bot_id");
            $table->string("result")->nullable();
            $table->string("type");
            $table->string("status");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('messages_concierges');
    }
};
