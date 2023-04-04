<?php

namespace App\Console\Commands\Reddy;

use App\Models\AgentBalance;
use App\Models\Status\AgentBalance\Created;
use App\Models\Status\AgentBalance\Handle;
use App\Services\ReddyBot\AgentsBotService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class SendButtonMyMethodToAllGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reddy:send-button';

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
        /**
         * @var AgentsBotService $reddyAgentBotService
         */
        $reddyAgentBotService = app(AgentsBotService::class);

        $balances = AgentBalance::query()->whereIn('id', function ($query) {
            return $query->from('agent_balances')->where('state', Created::name)->groupBy('chat_name')->selectRaw("MAX(id)");
        })
            ->get();

        foreach ($balances as $balance){
            $reddyAgentBotService->sendMessage('',$balance->chat_id,
                [
                    $reddyAgentBotService::createButton('/my-method','Please open my method',true),
                ]
            );
        }
    }
}
