<?php

namespace Modules\User\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * A staff member's role, or a role's permissions, changed. Neither is a column
 * of a model, so nothing else announces it.
 */
class StaffRolesChanged
{
    public function __construct(public readonly Model $model) {}
}
