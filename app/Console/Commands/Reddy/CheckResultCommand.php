<?php

namespace App\Console\Commands\Reddy;

use App\Jobs\Reddy\AgentBot\CancelNonHandledBalanceJob;
use App\Models\AgentBalance;
use App\Models\Status\AgentBalance\Finish;
use App\Models\Status\AgentBalance\Handle;
use App\Services\ReddyBot\AgentsBotService;
use App\Services\ReddyBot\LoggerService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class CheckResultCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reddy:result';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * @return void
     * @throws GuzzleException
     */
    public function handle(): void
    {

        /**
         * @var AgentsBotService $reddyService
         */
        $reddyService = app(AgentsBotService::class);
        $logger = app(LoggerService::class);
        $logger->log('generating results');

        try {
            $balances = AgentBalance::query()->whereIn('id', function ($query) {
                return $query->from('agent_balances')->where('state', Handle::name)->groupBy('chat_name')->selectRaw("MAX(id)");
            })
                ->get();

            if(!count($balances)) {
                throw new Exception('No balances available now');
            };

            $sum = 0;
            foreach ($balances as $balance) {
                $sum += $balance->balance;
            }
            $message = "[size=30]Daily report (result)[/size] \n";
            foreach ($balances as $balance) {
                $percentage = round(($balance->balance / $sum) * 100); // 20
                $message .= "[b]Name[/b]: $balance->name, [b]Percentage[/b]: $percentage%\n";
            }
            $balances->each(function ($balance) {
                $balance->state = Finish::name;
                $balance->save();
            });
            $reddyService->report($message);

            CancelNonHandledBalanceJob::dispatchSync();
        } catch (Exception $exception) {
            $logger->log($exception->getMessage());
        }
    }
}
