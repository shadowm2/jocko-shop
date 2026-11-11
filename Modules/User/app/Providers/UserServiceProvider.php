<?php

namespace Modules\User\Providers;

use App\Providers\BaseModuleServiceProvider;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\User\Console\SetRoleCommand;
use Modules\User\Enums\UserPermission;
use Modules\User\Livewire\Settings\DeleteUserForm;
use Modules\User\Models\User;

class UserServiceProvider extends BaseModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'User';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'user';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SetRoleCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();
        Livewire::component('user.settings.delete-user-form', DeleteUserForm::class);

        // Staff with no role keep full access, so shops that existed before roles are not locked out.
        Gate::before(fn (?User $user, string $ability): ?bool => $user !== null && in_array($ability, UserPermission::values(), true) && $user->roles->isEmpty() ? true : null);
    }
}
