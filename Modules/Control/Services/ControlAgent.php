<?php

namespace Modules\Control\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;
use Modules\Control\Exceptions\ControlException;

/**
 * What the scheduler runs: tell Jocko Control we are alive, then do whatever
 * it has queued for us. Network failures surface as ControlException and are
 * recorded in ControlLink; they never touch the shop's own data.
 */
#[Singleton]
class ControlAgent
{
    public function __construct(
        private readonly ControlClient $client,
        private readonly ControlLink $link,
        private readonly DeviceStatus $status,
        private readonly RemoteCommandRunner $runner,
    ) {}

    /**
     * Send a heartbeat and, if commands are waiting, run them.
     *
     * @return array{pending_commands: int, handled: int}
     *
     * @throws ControlException
     */
    public function heartbeat(): array
    {
        try {
            $response = $this->client->heartbeat($this->status->heartbeatPayload());
            $this->link->succeeded();
        } catch (ControlException $e) {
            $this->link->failed($e);

            throw $e;
        }

        $pending = (int) ($response['pending_commands'] ?? 0);

        return ['pending_commands' => $pending, 'handled' => $pending > 0 ? $this->processCommands() : 0];
    }

    /**
     * Fetch, run and acknowledge the queued commands.
     *
     * @return int how many commands were acknowledged
     *
     * @throws ControlException when the commands cannot be fetched
     */
    public function processCommands(): int
    {
        try {
            $commands = $this->client->commands((int) config('control.commands.max_per_run'));
        } catch (ControlException $e) {
            $this->link->failed($e);

            throw $e;
        }

        $acknowledged = 0;

        foreach ($commands as $command) {
            $id = $command['id'] ?? null;

            if (! is_string($id)) {
                continue;
            }

            $outcome = $this->runner->run($command);

            try {
                $this->client->acknowledge($id, $outcome['status'], $outcome['result'], $outcome['error']);
                $acknowledged++;
            } catch (ControlException $e) {
                // Not acknowledged means Jocko Control hands it out again, and the
                // handlers are safe to repeat. An expired command is a 409 here.
                Log::warning('A remote command could not be acknowledged.', ['command' => $id, 'code' => $e->errorCode, 'status' => $e->status]);
            }
        }

        return $acknowledged;
    }
}
