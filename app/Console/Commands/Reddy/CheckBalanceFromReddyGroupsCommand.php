<?php

namespace App\Console\Commands\Reddy;

use App\Exceptions\LoopBreakException;
use App\Services\ReddyBot\AgentsBotService;
use App\Services\ReddyBot\LoggerService;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Throwable;

class CheckBalanceFromReddyGroupsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reddy:getupdate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * @return void
     * @throws GuzzleException
     * @throws Throwable
     */
    public function handle(): void
    {

        $logger = app(LoggerService::class);
        $logger->log('loop started');
        /**
         * @var AgentsBotService $reddyAgentBotService
         */
        $reddyAgentBotService = app(AgentsBotService::class);

        $acceptingMethodTo = new Carbon(env('ACCEPT_METHODS_TO', '17:00'));
        $acceptingMethodTo->format('H:i');

        try {

            while (true) {

                foreach ($reddyAgentBotService->getUpdate() as $messageObject) {
                    try {

                        if ($messageObject->type !== 'message')
                        {
                            continue;
                        }

                        $message = $messageObject->message->msg;

                        if ($message === '/chat-id')
                        {
                            $reddyAgentBotService->sendMessage(
                                "chat id {$messageObject->chat->id}",
                                $messageObject->chat->id
                            );
                            continue;
                        }
                        if ($messageObject->chat->p2p)
                        {
                            if ($message === '/die') {
                                $logger->log("breaking loop");
                                throw new LoopBreakException("command die from {$messageObject->chat->id}");
                            }
                        } else if ($message === '/my-method')
                        {
                            $reddyAgentBotService->readMethod($messageObject);
                        } else
                        {
                            $reddyAgentBotService->readBalance($messageObject);
                        }

                    } catch (LoopBreakException $exception) {
                        throw new LoopBreakException($exception->getMessage());
                    } catch (Exception $exception) {
                        $logger->log($exception->getMessage());
                    }
                }
            }

        } catch (Exception $exception) {
            $logger->log("While loop is stopped error: " . $exception->getMessage());
        }

        $logger->log("While loop is cracked");
    }
}
