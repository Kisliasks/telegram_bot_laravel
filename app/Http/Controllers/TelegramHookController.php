<?php

namespace App\Http\Controllers;

use App\Models\TelegramBot;
use App\Services\TelegramBot\AbstractTelegramBotService;
use App\Services\TelegramBot\TelegramBirthDayService;
use Exception;
use Illuminate\Http\Request;
use Throwable;

class TelegramHookController extends Controller
{
    const availableTelegramServices = [
        'Office MSC Bot' => TelegramBirthDayService::class
    ];

    /**
     * @param Request $request
     * @param string $token
     * @return void
     * @throws Throwable
     */
    public function webhook(Request $request, string $token): void
    {
        /**
         * @var TelegramBot $bot
         */
        $bot = TelegramBot::query()->where('token', $token)->first();

        if (!isset(self::availableTelegramServices[$bot->name])) {
            throw new Exception('Service not found');
        }

        /**
         * @var AbstractTelegramBotService $class
         */
        $class = self::availableTelegramServices[$bot->name];
        $service = new $class($bot);
        
        $service->webhook($request);
    }
}
