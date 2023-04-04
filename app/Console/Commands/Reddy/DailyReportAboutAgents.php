<?php

namespace App\Console\Commands\Reddy;

use App\Models\AgentBalance;
use App\Models\Status\AgentBalance\Handle;
use App\Services\ReddyBot\AgentsBotService;
use App\Services\ReddyBot\LoggerService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DailyReportAboutAgents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reddy:balance-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return void
     * @throws GuzzleException
     */
    public function handle(): void
    {
        $logger = app(LoggerService::class);
        /**
         * @var AgentsBotService $reddyAgentService
         */
        $reddyAgentService = app(AgentsBotService::class);
        try {


            $balances = AgentBalance::query()->whereIn('id', function ($query) {
                return $query->from('agent_balances')->groupBy('chat_name')->whereDate('updated_at', Carbon::today())->selectRaw("MAX(id)");
            })->orderBy('state')->get();
            $count = $balances->count();

            if (!$count) {
                throw new \Exception('No records in db');
            }
            $message = "[size=30]Daily report[/size] \n";
            foreach ($balances as $key => $balance) {
                $message .= "[b]Name[/b]: {$balance->name}\n[b]Chat id[/b]: {$balance->chat_id}\n[b]Balance[/b]: {$balance->balance}\n[b]Status[/b]: {$balance->state}\n[b]Db_id[/b]: {$balance->id}\n";
                if ($count - 1 !== $key) {
                    $message .= "[b]____[/b]\n";
                }
            }
            $reddyAgentService->report($message);
        } catch (\Exception $exception) {
            $logger->log("Trying to create report :" . $exception->getMessage());
        }

    }
}
