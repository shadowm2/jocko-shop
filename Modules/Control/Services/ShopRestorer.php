<?php

namespace Modules\Control\Services;

use Closure;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schema;
use Modules\Control\Exceptions\ControlException;
use Modules\Dashboard\Models\Media;

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
     * exist: whoever set the new installation up has to be able to sign in.
     *
     * @return array<string, int> entity type => rows
     */
    public function existingData(): array
    {
        $existing = [];

        foreach ($this->restorable() as $type => $class) {
            if ($type === 'user') {
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
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $written;
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, mixed>  $record
     */
    private function write(string $class, array $record): void
    {
        $id = $record['id'] ?? null;

        if (! is_int($id)) {
            return;
        }

        /** @var Model $model */
        $model = $this->entities->queryWithDeleted($class)->find($id) ?? new $class;

        // The mirror never keeps the per-method totals of a daily close; the column is required.
        if (! $model->exists && $model instanceof \Modules\Order\Models\CashClose) {
            $record += ['totals' => []];
        }

        // Control sends UTC ISO 8601 ("...Z"); the columns want the app's own time zone.
        foreach (['created_at', 'updated_at', 'deleted_at'] as $column) {
            if (is_string($record[$column] ?? null)) {
                $record[$column] = Date::parse($record[$column])->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s');
            }
        }

        $model->timestamps = false;
        $model->forceFill($record);

        $class::withoutEvents(fn () => $model->save());
    }
}
