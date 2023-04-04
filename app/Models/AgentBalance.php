<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sub_agent_id',
        'balance',
        'type',
        'token',
        'chat_name',
        'chat_id',
        'message_timestamps',
        'state'
    ];

}
