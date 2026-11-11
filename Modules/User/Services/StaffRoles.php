<?php

namespace Modules\User\Services;

use Modules\User\Events\StaffRolesChanged;
use Modules\User\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Every change to who has which role, and to what a role may do, goes through
 * here so it is announced (and so synchronized to Jocko Control).
 */
class StaffRoles
{
    /** Give a staff member one role, or none (which keeps full access). */
    public function assign(User $user, ?string $role): void
    {
        $user->syncRoles($role === null || $role === '' ? [] : [$role]);

        event(new StaffRolesChanged($user));
    }

    /**
     * @param  list<string>  $permissions
     */
    public function setPermissions(Role $role, array $permissions): void
    {
        $role->syncPermissions($permissions);

        event(new StaffRolesChanged($role));
    }
}
