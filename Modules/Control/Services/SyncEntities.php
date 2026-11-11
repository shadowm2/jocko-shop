<?php

namespace Modules\Control\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Dashboard\Models\Media;
use Modules\User\Models\User;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Role;

/**
 * Which models are sent to Jocko Control and which of their fields may leave
 * this installation (config/control.php, `sync.entities`).
 */
#[Singleton]
class SyncEntities
{
    private const ALWAYS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * @return list<class-string<Model>>
     */
    public function models(): array
    {
        return array_keys($this->entities());
    }

    public function isSynced(Model $model): bool
    {
        return array_key_exists($model::class, $this->entities());
    }

    public function typeOf(Model $model): string
    {
        return $this->entities()[$model::class]['type'];
    }

    /**
     * @return list<string>
     */
    public function fieldsOf(Model $model): array
    {
        return array_merge(self::ALWAYS, $this->entities()[$model::class]['fields']);
    }

    /**
     * Every row of a synchronized model, soft-deleted ones included: a deleted
     * record is still a fact Jocko Control has to learn about.
     *
     * @param  class-string<Model>  $class
     * @return Builder<Model>
     */
    public function queryWithDeleted(string $class): Builder
    {
        return in_array(SoftDeletes::class, class_uses_recursive($class), true)
            ? $class::query()->withoutGlobalScope(SoftDeletingScope::class)
            : $class::query();
    }

    /**
     * The fields whose change is worth an event: the same list without the
     * bookkeeping columns, so touching a record or restoring it is not an update.
     *
     * @return list<string>
     */
    public function businessFieldsOf(Model $model): array
    {
        return array_values(array_diff($this->fieldsOf($model), self::ALWAYS));
    }

    /**
     * The complete allowlisted state of a record. Every event carries the whole
     * state, never a diff, so a lost or reordered event cannot corrupt the copy.
     *
     * @return array<string, mixed>
     */
    public function payloadOf(Model $model): array
    {
        $payload = array_intersect_key($model->attributesToArray(), array_flip($this->fieldsOf($model)));

        // A hidden attribute (the password hash) is left out of attributesToArray(); it is listed on purpose.
        foreach (array_intersect($model->getHidden(), $this->fieldsOf($model)) as $field) {
            $payload[$field] = $model->getAttributes()[$field] ?? null;
        }

        // Which roles a staff member has, and which permissions a role holds, are not columns.
        if ($model instanceof User) {
            $payload['roles'] = $model->roles()->orderBy('name')->pluck('name')->implode(',');
        } elseif ($model instanceof Role) {
            $payload['permissions'] = $model->permissions()->orderBy('name')->pluck('name')->implode(',');
        }

        // A picture says which record it belongs to by entity type, not by PHP class name.
        if ($model instanceof Media) {
            unset($payload['model_type'], $payload['model_id']);

            $payload['owner_type'] = $this->ownerType($model);
            $payload['owner_id'] = $model->model_id;
        }

        return $payload;
    }

    private function ownerType(Media $media): ?string
    {
        return $this->entities()[$media->model_type]['type'] ?? null;
    }

    /**
     * The same record always gets the same UUID, derived from its type and id,
     * so existing rows need no extra column to take part in synchronization.
     */
    public function aggregateId(string $type, int|string $id): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, "jocko-shop:{$type}:{$id}")->toString();
    }

    /**
     * @return array<class-string<Model>, array{type: string, fields: list<string>}>
     */
    private function entities(): array
    {
        /** @var array<class-string<Model>, array{type: string, fields: list<string>}> */
        return (array) config('control.sync.entities', []);
    }
}
