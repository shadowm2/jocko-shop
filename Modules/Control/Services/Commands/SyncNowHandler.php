<?php

namespace Modules\Control\Services\Commands;

use Modules\Control\Exceptions\CommandFailedException;

/**
 * ponytail: there is no event outbox yet, so there is nothing to push. Say so
 * instead of pretending. Replace this with a call into the outbox pusher once
 * business events are synchronized.
 */
class SyncNowHandler implements RemoteCommandHandler
{
    public function handle(array $payload): array
    {
        throw new CommandFailedException('Event synchronization is not enabled on this installation yet.');
    }
}
