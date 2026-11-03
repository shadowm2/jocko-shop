<?php

namespace Modules\Dashboard\Providers;

use App\Providers\BaseModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Dashboard\Console\ListBackupsCommand;
use Modules\Dashboard\Console\RestoreBackupCommand;
use Modules\Dashboard\Console\RunBackupCommand;
use Modules\Dashboard\Console\VerifyBackupCommand;
use Modules\Dashboard\Services\SettingService;

class DashboardServiceProvider extends BaseModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Dashboard';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'dashboard';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        RunBackupCommand::class,
        RestoreBackupCommand::class,
        ListBackupsCommand::class,
        VerifyBackupCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        resolve(SettingService::class)->applyToConfig();
    }

    /**
     * One backup a day, off the busy hours.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(RunBackupCommand::class)->dailyAt((string) config('backup.at'))->withoutOverlapping(60);
    }
}
