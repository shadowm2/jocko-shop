<?php

namespace Modules\User\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\User\Enums\UserRole;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;

#[Signature('user:role {mobile : The staff member\'s mobile number} {role : manager or cashier}')]
#[Description('Make a staff member a manager or a cashier')]
class SetRoleCommand extends Command
{
    public function handle(): int
    {
        $role = UserRole::tryFrom((string) $this->argument('role'));

        if ($role === null) {
            $this->components->error('The role must be manager or cashier.');

            return self::FAILURE;
        }

        $user = User::query()->where('type', UserType::Admin)->where('mobile', $this->argument('mobile'))->first();

        if ($user === null) {
            $this->components->error('No staff member has that mobile number.');

            return self::FAILURE;
        }

        $user->syncRoles([$role->value]);
        $this->components->info("{$user->first_name} {$user->last_name} is now a {$role->value}.");

        return self::SUCCESS;
    }
}
