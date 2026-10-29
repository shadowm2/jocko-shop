<?php

namespace Modules\Control\Services\Commands;

use Modules\Control\Exceptions\CommandFailedException;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Services\OutboxPusher;

/**
 * Sends whatever is waiting in the outbox right now instead of at the next
 * scheduled run. Repeating it is harmless.
 */
class SyncNowHandler implements RemoteCommandHandler
{
    public function __construct(private readonly OutboxPusher $pusher) {}

    public function handle(array $payload): array
    {
        try {
            return $this->pusher->push();
        } catch (ControlException $e) {
            throw new CommandFailedException($e->getMessage());
        }
    }
}
