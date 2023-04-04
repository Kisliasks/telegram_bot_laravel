<?php
namespace App\Contracts\Telegram;

interface TelegramBotServiceInterface
{
    public function webHook($request);
}
