<?php

namespace App\Models;

use DefStudio\Telegraph\Models\TelegraphBot as BaseModel;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TelegramBot extends BaseModel
{
    protected $table = 'telegraph_bots';

    public function setWebhook($hook): Response
    {
        $hook = str_replace('{token}', $this->token, $hook);

        return Http::get(
            'https://api.tlgr.org/bot' . $this->token . '/setWebhook?url=' . $hook
        );
    }
}
