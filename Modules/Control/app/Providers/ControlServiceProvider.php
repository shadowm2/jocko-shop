<?php

namespace Modules\Control\Providers;

use App\Providers\BaseModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\DevCommands;
use Modules\Control\Console\HeartbeatCommand;
use Modules\Control\Console\PollCommand;
use Modules\Control\Console\PushCommand;
use Modules\Control\Console\ResyncCommand;
use Modules\Control\Console\StatusCommand;
use Modules\Control\Observers\SyncedModelObserver;
use Modules\Control\Services\ControlClient;
use Modules\Control\Services\SyncEntities;

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
        PushCommand::class,
        ResyncCommand::class,
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

        foreach (resolve(SyncEntities::class)->models() as $model) {
            $model::observe(SyncedModelObserver::class);
        }

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

        // After the heartbeat, so a command such as sync_now and the regular push never overlap.
        $schedule->command(PushCommand::class)
            ->everyMinute()
            ->withoutOverlapping(5)
            ->when(fn (): bool => resolve(ControlClient::class)->isConfigured());
    }
}
