<?php

namespace App\Console\Commands\Telegram;

use App\Models\TelegramBot;
use Illuminate\Console\Command;

class SetWebHookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-web-hook {bot} {webhook}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /**
         * @var TelegramBot $bot
         */
        $bot = TelegramBot::findOrFail($this->argument('bot'));
        $bot->setWebhook($this->argument('webhook'));
        return 0;
    }
}
