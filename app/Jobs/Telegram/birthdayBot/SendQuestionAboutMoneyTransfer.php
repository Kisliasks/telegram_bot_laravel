<?php

namespace App\Jobs\Telegram\birthdayBot;

use App\Models\TelegramBot;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendQuestionAboutMoneyTransfer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        /**
         * @var TelegramBot $bot;
         */
       $bot = TelegramBot::query()->where('token',env('TELEGRAM_BIRTHDAY_BOT'))->first();
       //todo fix chat
       $res = Telegraph::message('Вы готовы начать работу?')->bot($bot)->chat(TelegraphChat::findOrFail(1))
            ->keyboard(Keyboard::make()->buttons([
                Button::make("В этот раз пропускаю")->action('YES'),
                Button::make("Отправил")->action('NO'),
            ])->chunk(2))->send();
    }
}
