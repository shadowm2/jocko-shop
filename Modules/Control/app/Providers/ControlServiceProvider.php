<?php

namespace Modules\Control\Providers;

use App\Providers\BaseModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\DevCommands;
use Modules\Control\Console\HeartbeatCommand;
use Modules\Control\Console\PollCommand;
use Modules\Control\Console\StatusCommand;
use Modules\Control\Services\ControlClient;

class ControlServiceProvider extends BaseModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Control';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'control';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        HeartbeatCommand::class,
        PollCommand::class,
        StatusCommand::class,
    ];

    /**
     * Provider classes to register. None: the module has no routes or events.
     *
     * @var string[]
     */
    protected array $providers = [];

    public function boot(): void
    {
        parent::boot();

        // `composer dev` should run the scheduler too, or the heartbeat never fires.
        if ($this->app->isLocal()) {
            DevCommands::artisan('schedule:work', 'schedule');
        }
    }

    /**
     * Report to Jocko Control every minute. Skipped entirely when the
     * installation is not configured, and a run that overlaps a slow one is dropped.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(HeartbeatCommand::class)
            ->everyMinute()
            ->withoutOverlapping(5)
            ->when(fn (): bool => resolve(ControlClient::class)->isConfigured());
    }
}
