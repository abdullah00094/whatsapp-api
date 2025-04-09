<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('ai_chat_histories', function (Blueprint $table) {
            $table->id();
            $table->string('sender_number'); // WhatsApp phone number
            $table->text('user_message');
            $table->text('ai_response')->nullable(); // AI might not respond instantly
            $table->timestamps();
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a_i_chat_histories');
    }
};
