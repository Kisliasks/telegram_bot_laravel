<?php

namespace App\Console\Commands\Telegram\BirthdayBot;


use App\Models\TelegramBot;
use App\Services\TelegramBot\TelegramBirthDayService;
use Illuminate\Console\Command;

class WorkStatusTimeOut extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:timeout-workstatus';

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
       $service->getbuttonsWorkDayTimeOut();
    }
    
}