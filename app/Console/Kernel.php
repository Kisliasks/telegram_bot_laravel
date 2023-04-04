<?php

namespace App\Console;

use App\Console\Commands\Reddy\CheckResultCommand;
use App\Console\Commands\Reddy\DailyReportAboutAgents;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command(CheckResultCommand::class)->dailyAt(env('CHECK_RESULT_AT','17:01'));
        // $schedule->command(DailyReportAboutAgents::class)->dailyAt(env('CHECK_RESULT_AT','17:01'));
        $schedule->command('telegram:send-workday-buttons')->timezone('Europe/Moscow')->weekdays()->dailyAt('14:21'); // send workday buttons
        $schedule->command('telegram:unset-workstatus')->timezone('Europe/Moscow')->weekdays()->dailyAt('12:12'); // unset workday status
        $schedule->command('telegram:timeout-workstatus')->timezone('Europe/Moscow')->weekdays()->dailyAt('14:22'); // timeout workday response
        $schedule->command('telegram:statistics-workday')->timezone('Europe/Moscow')->weekdays()->dailyAt('14:22'); // statistics workday response
        $schedule->command('telegram:send-birthday-messages')->timezone('Europe/Moscow')->dailyAt('14:23'); // send birthday buttons
        $schedule->command('telegram:timeout-birthdaymessages')->timezone('Europe/Moscow')->dailyAt('14:24'); // timeout birthday response
        $schedule->command('telegram:statistics-birthday')->timezone('Europe/Moscow')->dailyAt('14:24'); // statistics birthday response
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
