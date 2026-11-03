<?php

namespace Modules\Order\Payments;

use RuntimeException;

/**
 * A gateway could not be used right now (unreachable, refused, answered something
 * unexpected). The message is safe to show: it never contains a credential.
 */
class GatewayException extends RuntimeException {}
