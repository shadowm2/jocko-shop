<?php

namespace Modules\Order\Providers;

use App\Providers\BaseModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Order\Console\ReconcilePaymentsCommand;
use Modules\Order\Console\RemindServicesCommand;

class OrderServiceProvider extends BaseModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Order';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'order';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ReconcilePaymentsCommand::class,
        RemindServicesCommand::class,
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

    /**
     * A customer who paid but never came back to the shop is still found.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(ReconcilePaymentsCommand::class)->everyMinute()->withoutOverlapping(5);
        $schedule->command(RemindServicesCommand::class)->dailyAt('09:30')->withoutOverlapping(60);
    }
}
