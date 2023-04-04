<?php

namespace App\Jobs\Reddy\AgentBot;

use App\Models\AgentBalance;
use App\Models\Status\AgentBalance\Canceled;
use App\Models\Status\AgentBalance\Created;
use App\Models\Status\AgentBalance\Handle;
use App\Services\ReddyBot\LoggerService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CancelNonHandledBalanceJob implements ShouldQueue
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
        $logger = app(LoggerService::class);
        try {
             AgentBalance::query()
                ->where('state', '=', Created::name)
                ->orWhere('state', '=', Handle::name)
                ->whereTime('created_at', '<', Carbon::parse(env('ACCEPT_METHODS_TO', '17:00')))
                ->update(['state' => Canceled::name]);

            $logger->log('Canceling all non handled balance');

        } catch (\Throwable $e) {
            $logger->log('Error while canceling balance: ' . $e->getMessage());
        }
    }
}
