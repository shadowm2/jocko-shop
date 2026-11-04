<?php

namespace Modules\Order\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Order\Services\ServiceReminderService;

#[Signature('service:remind')]
#[Description('Text customers whose car is due back for its next service')]
class RemindServicesCommand extends Command
{
    public function handle(ServiceReminderService $reminders): int
    {
        $result = $reminders->remindDue();

        $this->components->info("Service reminders: {$result['sent']} sent, {$result['skipped']} skipped (no mobile), {$result['failed']} failed.");

        return self::SUCCESS;
    }
}
