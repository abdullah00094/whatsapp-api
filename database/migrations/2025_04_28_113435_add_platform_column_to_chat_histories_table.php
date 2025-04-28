<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_chat_histories', function (Blueprint $table) {
            $table->string('platform')->default('web')->after('sender_number'); // Adjust table name if needed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_chat_histories', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};
