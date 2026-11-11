<?php

namespace Modules\Control\Services;

use Closure;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schema;
use Modules\Control\Exceptions\ControlException;
use Modules\Dashboard\Models\Media;
use Modules\User\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Rebuilds a lost installation from the copy Jocko Control keeps.
 *
 * Only what Control mirrors comes back: the allowlisted fields of the
 * business records. Passwords (staff sign in with an SMS code), settings,
 * roles and the picture files are never sent to Control, so they cannot be
 * restored; the picture records are skipped because they would point at
 * files that are not here.
 *
 * Records are written without firing the sync observer: Control already has
 * them, so nothing is sent back.
 */
#[Singleton]
class ShopRestorer
{
    /** @var array<int, list<string>> user id => role names, applied at the end */
    private array $pendingRoles = [];

    public function __construct(
        private readonly ControlClient $client,
        private readonly SyncEntities $entities,
    ) {}

    /**
     * The entity types that can be restored, entity type => model class.
     *
     * @return array<string, class-string<Model>>
     */
    public function restorable(): array
    {
        $types = [];

        foreach ($this->entities->models() as $class) {
            if ($class !== Media::class) {
                $types[$this->entities->typeOf(new $class)] = $class;
            }
        }

        return $types;
    }

    /**
     * Entity types that already have rows here. Staff accounts are allowed to
     * exist (whoever set the new installation up has to be able to sign in), and
     * so are the built-in roles every installation starts with.
     *
     * @return array<string, int> entity type => rows
     */
    public function existingData(): array
    {
        $existing = [];

        foreach ($this->restorable() as $type => $class) {
            if (in_array($type, ['user', 'role'], true)) {
                continue;
            }

            $count = $this->entities->queryWithDeleted($class)->count();

            if ($count > 0) {
                $existing[$type] = $count;
            }
        }

        return $existing;
    }

    /**
     * How many records Control can give back per entity type.
     *
     * @return array<string, int>
     *
     * @throws ControlException
     */
    public function available(): array
    {
        $counts = $this->client->restoreSummary()['entities'] ?? [];

        return array_map('intval', array_intersect_key((array) $counts, $this->restorable()));
    }

    /**
     * @param  Closure(string, int): void|null  $progress  called with the entity type and records written so far
     * @return array<string, int> records written per entity type
     *
     * @throws ControlException
     */
    public function restore(?Closure $progress = null): array
    {
        $written = [];
        $this->pendingRoles = [];

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($this->available() as $type => $total) {
                $class = $this->restorable()[$type];
                $written[$type] = 0;
                $after = 0;

                do {
                    $page = $this->client->restoreRecords($type, $after);

                    foreach ($page['records'] as $record) {
                        $this->write($class, $record);
                        $written[$type]++;
                    }

                    $after = (int) $page['next'];

                    if ($progress !== null) {
                        $progress($type, $written[$type]);
                    }
                } while ($page['next'] !== null);
            }

            $this->assignRoles();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $written;
    }

    /**
     * A role is found or created by name (a fresh install already has manager and cashier),
     * and holds the permissions it had.
     *
     * @param  array<string, mixed>  $record
     */
    private function writeRole(array $record): void
    {
        $name = $record['name'] ?? null;

        if (! is_string($name) || $name === '') {
            return;
        }

        $role = Role::withoutEvents(fn () => Role::findOrCreate($name, is_string($record['guard_name'] ?? null) ? $record['guard_name'] : 'web'));
        $known = Permission::query()->whereIn('name', $this->names($record['permissions'] ?? null))->pluck('name')->all();

        Role::withoutEvents(fn () => $role->syncPermissions($known));
    }

    /**
     * Roles are applied once every role exists.
     */
    private function assignRoles(): void
    {
        foreach ($this->pendingRoles as $userId => $names) {
            $existing = Role::query()->whereIn('name', $names)->pluck('name')->all();

            User::withoutEvents(fn () => User::query()->find($userId)?->syncRoles($existing));
        }
    }

    /**
     * @return list<string>
     */
    private function names(mixed $list): array
    {
        return is_string($list) && $list !== '' ? array_values(array_filter(explode(',', $list))) : [];
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, mixed>  $record
     */
    private function write(string $class, array $record): void
    {
        if ($class === Role::class) {
            $this->writeRole($record);

            return;
        }

        $id = $record['id'] ?? null;

        if (! is_int($id)) {
            return;
        }

        /** @var Model $model */
        $model = $this->entities->queryWithDeleted($class)->find($id) ?? new $class;

        // Not a column: which roles the staff member had, applied once all roles exist.
        $roles = $class === User::class ? $this->names(Arr::pull($record, 'roles')) : [];

        // The mirror never keeps the per-method totals of a daily close; the column is required.
        if (! $model->exists && $model instanceof \Modules\Order\Models\CashClose) {
            $record += ['totals' => []];
        }

        // Control sends UTC ISO 8601 ("...Z"); the columns want the app's own time zone.
        $inDatabaseForm = fn (mixed $value): mixed => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T/', $value) === 1
            ? Date::parse($value)->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s')
            : $value;

        foreach (['created_at', 'updated_at', 'deleted_at'] as $column) {
            if (array_key_exists($column, $record)) {
                $record[$column] = $inDatabaseForm($record[$column]);
            }
        }

        // A field with its own setter (a car's manufacture date takes a Jalali date from a form)
        // expects form input, not the stored value Control hands back: write those as they are.
        $raw = [];

        foreach ($record as $column => $value) {
            if ($model->hasSetMutator($column)) {
                $raw[$column] = $inDatabaseForm($value);
                unset($record[$column]);
            }
        }

        $model->timestamps = false;
        $model->forceFill($record);

        if ($raw !== []) {
            $model->setRawAttributes([...$model->getAttributes(), ...$raw]);
        }

        $class::withoutEvents(fn () => $model->save());

        if ($roles !== []) {
            $this->pendingRoles[$id] = $roles;
        }
    }
}
