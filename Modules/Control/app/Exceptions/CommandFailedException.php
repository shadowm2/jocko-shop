<?php

namespace Modules\Control\Exceptions;

use RuntimeException;

/**
 * A remote command could not be carried out, for an expected reason. The
 * message is reported back to Jocko Control as the command's error.
 */
class CommandFailedException extends RuntimeException {}
