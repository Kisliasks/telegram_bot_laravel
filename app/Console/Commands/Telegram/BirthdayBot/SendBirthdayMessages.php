<?php

namespace App\Console\Commands\Telegram\BirthdayBot;


use App\Models\TelegramBot;
use App\Services\TelegramBot\TelegramBirthDayService;
use Illuminate\Console\Command;

class SendBirthdayMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:send-birthday-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        
        $bot = TelegramBot::query()->where('name', 'Office MSC Bot')->first();
       $class = TelegramBirthDayService::class;
       $service = new $class($bot);
       $service->getsendBirthdayMessages();
    }
    
}