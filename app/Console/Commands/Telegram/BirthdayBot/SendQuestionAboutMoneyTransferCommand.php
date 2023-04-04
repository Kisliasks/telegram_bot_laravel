<?php

namespace App\Console\Commands\Telegram\BirthdayBot;

use App\Jobs\Telegram\birthdayBot\SendQuestionAboutMoneyTransfer;
use Illuminate\Console\Command;

class SendQuestionAboutMoneyTransferCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:send-question';

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
        SendQuestionAboutMoneyTransfer::dispatchSync();
    }
}
