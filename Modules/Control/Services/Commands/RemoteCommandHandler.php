<?php

namespace Modules\Control\Services\Commands;

use Modules\Control\Exceptions\CommandFailedException;

/**
 * Carries out one allowlisted remote command type.
 *
 * Jocko Control keeps handing a command out until it is acknowledged, so a
 * handler must be safe to run twice. Handlers never run anything supplied by
 * the server: they only read local state or do a fixed, built-in action.
 */
interface RemoteCommandHandler
{
    /**
     * @param  array<string, mixed>  $payload  the (already server-validated) parameters
     * @return array<string, mixed> reported back as the command's result
     *
     * @throws CommandFailedException for an expected failure; the message is reported
     */
    public function handle(array $payload): array;
}
