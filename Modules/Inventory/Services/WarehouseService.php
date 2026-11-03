<?php

namespace Modules\Inventory\Services;

use App\Helpers\Utils;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Dashboard\Services\SettingService;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Repositories\WarehouseRepository;

/**
 * @extends BaseService<Warehouse, WarehouseRepository>
 */
class WarehouseService extends BaseService
{
    public function __construct(
        WarehouseRepository $repository
    ) {
        parent::__construct($repository);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return ($paginate is true ? LengthAwarePaginator<int, Warehouse> : Collection<int, Warehouse>)
     */
    public function getWarehouses(array $filters = [], bool $paginate = true): LengthAwarePaginator|Collection
    {
        $this->repository
            ->filtered($filters)
            ->orderBy('warehouse_items_count', \SortDirection::Descending)
            ->orderBy('name')
            ->withRelations();

        return $paginate ? $this->repository->paginate() : $this->repository->all();
    }

    public function create(array $data): Warehouse
    {
        $data['slug'] ??= Utils::generateUniqueSlug($data['name'], Warehouse::class);

        return parent::create($data);
    }

    public const SETTING_MULTIPLE = 'warehouses.multiple';

    public const SETTING_DEFAULT = 'warehouses.default';

    /**
     * Whether this shop works with several warehouses (pickers, transfers, the
     * warehouses menu). Until someone decides in the settings, a shop that
     * already has more than one warehouse keeps working as before.
     */
    public function isMultiple(): bool
    {
        $setting = resolve(SettingService::class)->all()[self::SETTING_MULTIPLE] ?? null;

        return $setting === null ? Warehouse::query()->count() > 1 : $setting === '1';
    }

    /**
     * The warehouse chosen in the settings, when it still exists.
     */
    public function defaultWarehouse(): ?Warehouse
    {
        $id = resolve(SettingService::class)->all()[self::SETTING_DEFAULT] ?? null;

        return is_numeric($id) ? Warehouse::query()->find((int) $id) : null;
    }

    /**
     * The warehouse every record goes to while only one is in use, or null when
     * several are (the user picks then).
     */
    public function fixedWarehouse(): ?Warehouse
    {
        return $this->isMultiple() ? null : $this->getActiveWarehouse();
    }

    /**
     * The warehouses menu stays until a default is chosen, so a shop that has no
     * warehouse yet can still create one.
     */
    public function showsWarehouseMenu(): bool
    {
        return $this->isMultiple() || $this->defaultWarehouse() === null;
    }

    public function getActiveWarehouse(): ?Warehouse
    {
        if (! $this->isMultiple()) {
            return $this->defaultWarehouse() ?? $this->mostStockedWarehouse();
        }

        $cacheKey = 'active_warehouse';
        $activeWarehouseId = Cache::get($cacheKey);
        if (is_numeric($activeWarehouseId) && (int) $activeWarehouseId == $activeWarehouseId) {

            return Warehouse::find($activeWarehouseId);
        }

        $activeWarehouse = $this->mostStockedWarehouse();
        if ($activeWarehouse) {
            Cache::forever($cacheKey, $activeWarehouse->id);
        }

        return $activeWarehouse;
    }

    private function mostStockedWarehouse(): ?Warehouse
    {
        return Warehouse::withCount('warehouseItems')
            ->orderByDesc('warehouse_items_count')
            ->orderBy('created_at')
            ->first();
    }

    public function setActiveWarehouse(Warehouse|string $warehouse): bool
    {
        $cacheKey = 'active_warehouse';
        if (is_string($warehouse)) {
            $warehouse = $this->findByKeyOrFail($warehouse);
        }
        $activeWarehouseId = $warehouse->id;

        return Cache::forever($cacheKey, $warehouse->id);
    }
}
