<?php

namespace App\Services\ReddyBot;

use App\Models\AgentBalance;
use App\Models\Status\AgentBalance\Created;
use App\Models\Status\AgentBalance\Handle;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

class AgentsBotService extends AbstractReddyBotService
{

    const MINIMAL_ALLOWED_BALANCE = 250000;
    public array $reportTo = [];

    public function __construct($token = null, $reportTo = [])
    {

        $this->token = $token ?: env('REDDY_BOT_AGENTS');
        $this->reportTo = $reportTo ?: explode(',', env('REDDY_BOT_AGENTS_REPORT_TO'));
    }

    /**
     * @throws Exception
     */
    public function readBalance($messageObject)
    {
        $message = $messageObject->message->msg;
        $chatId = $messageObject->chat->id;
        $chatName = $messageObject->chat->title;
        $messageTimestamps = $messageObject->ts;
        /**
         * from string to ruble
         *
         */
        $balance = substr(
            $message,
            strpos($message, 'BDT'),
            strlen($message)
        );

        $doubleValueInRuble = (double)str_replace(',', '.', preg_replace('/[^0-9,.]+/', '', $balance));
        $fullName = substr($message, 0, strpos($message, '(SubAgent'));
        $subAgentId = substr($message, 0, strpos($message, 'Balance'));
        $subAgentId = str_replace(',', '.', preg_replace('/[^0-9,.]+/', '', $subAgentId));
        $logger = app(LoggerService::class);

        if (!$doubleValueInRuble || !$fullName || !$subAgentId) {
            return;
        }

        if ($doubleValueInRuble < self::MINIMAL_ALLOWED_BALANCE) {
            throw new Exception("balance is lower than " . self::MINIMAL_ALLOWED_BALANCE);
        }

        $logger->log("Creating balance for {$fullName}");

        AgentBalance::query()->create([
            'name' => $fullName,
            'sub_agent_id' => $subAgentId,
            'balance' => $doubleValueInRuble,
            'type' => '',
            'state' => Created::name,
            'token' => $this->token,
            'chat_id' => $chatId,
            'chat_name' => $chatName,
            'message_timestamps' => $messageTimestamps,
        ]);

    }

    /**
     * @throws Throwable
     */
    public function readMethod($messageObject)
    {
        $logger = app(LoggerService::class);
        $now = Carbon::now('Europe/moscow')->format('H:i');

        $acceptingMethodFrom = new Carbon(env('ACCEPT_METHODS_FROM', '16:30'));
        $acceptingMethodFrom->format('H:i');

        $acceptingMethodTo = new Carbon(env('ACCEPT_METHODS_TO', '17:00'));
        $acceptingMethodTo->format('H:i');

        $inTime =
            $acceptingMethodFrom->toTimeString() < $now
            &&
            $acceptingMethodTo->toTimeString() >= $now;

        if (!$inTime)
        {
            $logger->log("my-method came not in time. Chat name: {$messageObject->chat->title}");
            return;
        }
        $chatName = $messageObject->chat->title;
        $logger->log("Accepting method from {$chatName}");
        $agentBalance = AgentBalance::query()
            ->where('chat_name', $chatName)
            ->where('state', Created::name)
            ->latest()
            ->first();
        if (!$agentBalance) {
            throw new Exception('agent balance not exist. Chat name: ' . $chatName);
        }
        $agentBalance->state = Handle::name;
        $agentBalance->saveOrFail();
    }


    /**
     * @throws GuzzleException
     */
    public function report($message)
    {
        foreach ($this->reportTo as $chatId) {
            $this->sendMessage($message, $chatId);
        }
    }
}
