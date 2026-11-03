<?php

namespace Modules\Order\Payments;

enum VerifyOutcome: string
{
    /** The gateway confirms the money was paid. */
    case PAID = 'paid';

    /** Nothing paid (yet): the customer may still be on the bank page. */
    case NOT_PAID = 'not_paid';

    /** The gateway says it will never be paid, or the details do not match. */
    case FAILED = 'failed';
}
