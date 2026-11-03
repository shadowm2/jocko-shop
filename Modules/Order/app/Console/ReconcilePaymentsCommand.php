<?php

namespace Modules\Order\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Order\Services\PaymentAttemptService;

#[Signature('payments:reconcile')]
#[Description('Ask the payment gateways about every open online payment and give up on the ones that ran out of time')]
class ReconcilePaymentsCommand extends Command
{
    public function handle(PaymentAttemptService $attempts): int
    {
        $result = $attempts->reconcileOpen();

        $this->components->info("Checked {$result['checked']} open payment(s): {$result['completed']} paid, {$result['expired']} expired.");

        return self::SUCCESS;
    }
}
