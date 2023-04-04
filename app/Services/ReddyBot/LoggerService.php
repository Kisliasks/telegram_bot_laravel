<?php

namespace App\Services\ReddyBot;

use Exception;
use GuzzleHttp\Exception\GuzzleException;

class LoggerService extends AbstractReddyBotService
{

    public array $chats = [];

    public function __construct($token = null, $chats = [])
    {
        $this->token = $token ?: env('REDDY_BOT_LOGGER_TOKEN');
        $this->chats = $chats ?: explode(',',env('REDDY_BOT_LOGGER_CHAT'));
    }


    /**
     */
    public function log($message, $reportTo = null){

        if(!$reportTo){
            $reportTo = $this->chats;
        }
        try {
            foreach ($reportTo as $chat){
                $this->sendMessage($message,$chat);
            }
        }catch (Exception|GuzzleException $exception){
        }
    }
}
