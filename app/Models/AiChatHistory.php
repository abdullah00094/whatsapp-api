<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AiChatHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sender_number',
        'user_message',
        'ai_response',
    ];
}
