<?php

namespace Modules\User\Models;

use App\Models\BaseAuthenticatableModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\User\Database\Factories\UserFactory;
use Modules\User\Enums\UserRole;
use Modules\User\Enums\UserType;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $slug
 * @property UserType $type
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $mobile
 * @property string|null $otp_code
 * @property Carbon|null $otp_issued_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseAuthenticatableModel<User>
 */
#[Fillable(['slug', 'first_name', 'last_name', 'mobile', 'password', 'email', 'type'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'otp_code'])]
class User extends BaseAuthenticatableModel
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_issued_at' => 'datetime',
            'password' => 'hashed',
            'type' => UserType::class,
        ];
    }

    /** Cashiers work the counter only; staff without a role keep full access. */
    public function isCashier(): bool
    {
        return $this->hasRole(UserRole::Cashier->value);
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function admin(Builder $query): void
    {
        $query->where('type', UserType::Admin);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->fullName(), true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function fullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
