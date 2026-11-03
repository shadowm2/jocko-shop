<?php

namespace Modules\Order\Payments;

/**
 * What a gateway answered when asked whether a payment went through.
 */
final readonly class GatewayVerification
{
    public function __construct(
        public VerifyOutcome $outcome,
        public ?string $reference = null,
        public ?string $cardPan = null,
        public ?string $message = null,
    ) {}

    public static function paid(?string $reference, ?string $cardPan = null): self
    {
        return new self(VerifyOutcome::PAID, $reference, $cardPan);
    }

    public static function notPaid(?string $message = null): self
    {
        return new self(VerifyOutcome::NOT_PAID, message: $message);
    }

    public static function failed(?string $message = null): self
    {
        return new self(VerifyOutcome::FAILED, message: $message);
    }
}
