<?php

namespace Modules\Order\Services;

use App\Helpers\Utils;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Car\Models\UserCar;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Services\StockMovementService;
use Modules\Inventory\Services\WarehouseItemService;
use Modules\Inventory\Services\WarehouseService;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Repositories\OrderRepository;
use Modules\User\Models\User;
use Modules\User\Services\UserCarService;
use Modules\User\Services\UserService;

/**
 * @extends BaseService<Order, OrderRepository>
 */
class OrderService extends BaseService
{
    public function __construct(
        OrderRepository $repository
    ) {
        parent::__construct($repository);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Order>|Collection<int, Order>
     */
    public function getOrders(array $filters = [], bool $paginate = true): LengthAwarePaginator|Collection
    {
        $this->repository
            ->filtered($filters)
            ->withRelations();

        return $paginate ? $this->repository->paginate() : $this->repository->all();
    }

    /**
     * @throws \Throwable
     */
    public function create(array $data): Order
    {
        /** @var User $user */
        $user = resolve(UserService::class)->find($data['user_id']);
        /** @var UserCar $userCar */
        $userCar = resolve(UserCarService::class)->find($data['user_car_id']);

        $data['slug'] ??= Utils::generateUniqueSlug($user->initials().' '.$userCar->car->name, Order::class);
        $data['order_number'] ??= Order::nextNumber();
        $items = Arr::pull($data, 'items', []);
        $services = Arr::pull($data, 'services', []);

        return DB::transaction(function () use ($data, $items, $services) {
            /** @var Order $order */
            $order = parent::create($data);
            $this->syncItems($order, $items);
            $this->syncServices($order, $services);
            $this->syncStock($order);

            return $order->refresh();
        });
    }

    public function update(int|Model $model, array $data): bool
    {
        if ($model instanceof Model === false) {
            $model = $this->findOrFail($model);
        }

        // Absent items leave the lines untouched; an empty array clears them.
        $items = Arr::pull($data, 'items');
        $services = Arr::pull($data, 'services');

        return DB::transaction(function () use ($model, $data, $items, $services) {
            $updated = parent::update($model, $data);

            if (is_array($items)) {
                $this->syncItems($model, $items);
            }

            if (is_array($services)) {
                $this->syncServices($model, $services);
            }

            // Also on a status-only change: cancelling gives the stock back.
            $this->syncStock($model);

            return $updated;
        });
    }

    /**
     * Reconcile an order's line items with the given lines.
     *
     * Each entry is keyed by warehouse item slug and carries its own
     * quantity and price. Kept lines are updated in place, dropped lines
     * are soft deleted, and a line selected again after removal is
     * restored rather than duplicated.
     *
     * @param  array<string, array<string, mixed>>  $lines
     */
    protected function syncItems(Order $order, array $lines): void
    {
        $warehouseItems = resolve(WarehouseItemService::class)
            ->getWarehouseItems([
                'warehouse_id' => $order->warehouse_id,
                'whereIn' => ['slug', array_keys($lines)],
            ], paginate: false)
            ->keyBy('slug');

        $missing = array_diff(array_keys($lines), $warehouseItems->keys()->all());
        if ($missing !== []) {
            throw new InvalidArgumentException('Warehouse items not found in the order warehouse: '.implode(', ', $missing));
        }

        $itemIds = $warehouseItems->pluck('item_id')->all();

        // Service lines are not warehouse items and are kept by syncServices().
        $order->items()->whereNotIn('item_id', $itemIds)->whereDoesntHave('item', fn ($item) => $item->where('is_service', true))->delete();
        $order->items()->onlyTrashed()->whereIn('item_id', $itemIds)->restore();

        $existing = $order->items()->get()->keyBy('item_id');

        foreach ($warehouseItems as $slug => $warehouseItem) {
            $values = $this->lineValues($lines[$slug]);

            if ($line = $existing->get($warehouseItem->item_id)) {
                $line->update($values);

                continue;
            }

            $order->items()->create([
                'slug' => Utils::generateUniqueSlug('oi '.$order->slug.' '.$warehouseItem->slug, OrderItem::class),
                'item_id' => $warehouseItem->item_id,
                ...$values,
            ]);
        }
    }

    /**
     * Reconcile the service lines (labour and the like) of an order.
     *
     * Each entry is keyed by the service's slug and carries its quantity, price
     * and optionally who did it. A service has no stock, so these lines never
     * touch the warehouse.
     *
     * @param  array<string, array<string, mixed>>  $lines
     */
    protected function syncServices(Order $order, array $lines): void
    {
        $services = Item::query()->with('unitGroup.units')->where('is_service', true)->whereIn('slug', array_keys($lines))->get()->keyBy('slug');

        $missing = array_diff(array_keys($lines), $services->keys()->all());
        if ($missing !== []) {
            throw new InvalidArgumentException('Services not found: '.implode(', ', $missing));
        }

        $ids = $services->pluck('id')->all();

        $order->items()->whereIn('item_id', Item::query()->where('is_service', true)->select('id'))->whereNotIn('item_id', $ids)->delete();
        $order->items()->onlyTrashed()->whereIn('item_id', $ids)->restore();

        $existing = $order->items()->get()->keyBy('item_id');

        foreach ($services as $slug => $service) {
            $line = $lines[$slug];
            $units = $service->unitGroup->units;
            $values = $this->lineValues($line + ['unit_id' => ($units->firstWhere('is_base', true) ?? $units->first())?->id]) + [
                'mechanic_id' => filled($line['mechanic_id'] ?? null) ? (int) $line['mechanic_id'] : null,
            ];

            if ($current = $existing->get($service->id)) {
                $current->update($values);

                continue;
            }

            $order->items()->create([
                'slug' => Utils::generateUniqueSlug('os '.$order->slug.' '.$service->slug, OrderItem::class),
                'item_id' => $service->id,
                ...$values,
            ]);
        }
    }

    /**
     * Active orders hold their lines' stock; a cancelled order holds none.
     * Throws a ValidationException when a line exceeds what is on hand.
     */
    protected function syncStock(Order $order): void
    {
        /** @var Collection<int, OrderItem> $lines */
        $lines = $order->status === OrderStatus::CANCELLED
            ? new Collection
            : $order->items()->whereDoesntHave('item', fn ($item) => $item->where('is_service', true))->with(['unit', 'item.unitGroup.units'])->get();

        resolve(StockMovementService::class)->syncSale($order, $order->warehouse_id, $lines);
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    protected function lineValues(array $line): array
    {
        $quantity = (float) $line['quantity'];
        $unitPrice = (float) $line['unit_price'];

        return [
            'unit_id' => filled($line['unit_id'] ?? null) ? (int) $line['unit_id'] : null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            // Total is derived, never taken from the request.
            'total' => round($quantity * $unitPrice, 2),
        ];
    }

    protected function interpretData(array $data): array
    {
        if (! empty($data['warehouse'])) {
            $data['warehouse_id'] = resolve(WarehouseService::class)->findByKeyOrFail($data['warehouse'])->id;
        }
        unset($data['warehouse']);

        return parent::interpretData($data);
    }
}
