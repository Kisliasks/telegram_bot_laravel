<?php

namespace App\Services\TelegramBot;

use App\Contracts\Telegram\TelegramBotServiceInterface;
use App\Models\TelegramBot;

abstract class AbstractTelegramBotService implements TelegramBotServiceInterface
{
    public TelegramBot $bot;

    public function __construct($bot)
    {
        $this->bot = $bot;
    }

    /**
     * @param $request
     * @return void
     */
    public function webHook($request): void
    {
        if ($callback_query = $request->input('callback_query')) {
            $this->readAction((object)$callback_query);
        } elseif ($message = $request->input('message')) {
            $this->readUserAnswer((object)$request->input('message'));
            
            if (isset($message['left_chat_member'])) {
                $this->userLogOut($message);
            } elseif (isset($message['new_chat_member'])) {
                $this->userLogIn($message);
            }
        }
    }

    protected abstract function readUserAnswer(object $callback_query);
    protected abstract function readAction(object $callback_query);
    protected abstract function userLogOut(array $request);
    protected abstract function userLogIn(array $request);
    protected abstract function workDayResponse(object $callback_query);
    protected abstract function birthdayResponse(object $callback_query);
    

   





    

}
