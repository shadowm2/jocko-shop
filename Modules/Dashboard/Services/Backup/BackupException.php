<?php

namespace Modules\Dashboard\Services\Backup;

use RuntimeException;

/**
 * A backup could not be made or read. The message is safe to show: it never
 * contains the key or any data.
 */
class BackupException extends RuntimeException {}
