<?php

namespace Modules\Control\Services\Commands;

use Modules\Control\Exceptions\CommandFailedException;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Services\OutboxPusher;
use Modules\Control\Services\OutboxSnapshot;

/**
 * Sends whatever is waiting in the outbox right now instead of at the next
 * scheduled run. With `full`, it first queues a fresh copy of every synchronized
 * record (the same as `control:resync`), for when Jocko Control lost or deleted
 * its copy. Repeating it is harmless.
 */
class SyncNowHandler implements RemoteCommandHandler
{
    public function __construct(
        private readonly OutboxPusher $pusher,
        private readonly OutboxSnapshot $snapshot,
    ) {}

    public function handle(array $payload): array
    {
        $queued = ($payload['full'] ?? false) === true ? array_sum($this->snapshot->enqueue()) : 0;

        try {
            return ['queued' => $queued] + $this->pusher->push();
        } catch (ControlException $e) {
            throw new CommandFailedException($e->getMessage());
        }
    }
}
