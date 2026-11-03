<?php

namespace Modules\Order\Payments;

/**
 * A payment the gateway has accepted: its own token for it, and the page the
 * customer pays on.
 */
final readonly class GatewayStart
{
    public function __construct(
        public string $reference,
        public string $url,
    ) {}
}
