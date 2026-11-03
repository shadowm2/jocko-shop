<?php

namespace Modules\Order\Livewire\Forms;

use App\Helpers\Utils;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;
use Modules\Inventory\Livewire\Concerns\UsesFixedWarehouse;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Inventory\Services\WarehouseItemService;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Services\OrderService;

class OrderForm extends Form
{
    use UsesFixedWarehouse;

    public ?Order $order = null;

    public ?int $user_id = null;

    public ?int $user_car_id = null;

    public string $warehouse = '';

    public string $status = OrderStatus::PENDING->value;

    public ?string $description = null;

    /**
     * Warehouse item slugs currently chosen in the picker.
     *
     * @var array<int, string>
     */
    public array $selected = [];

    /**
     * Per line values (quantity, unit_price, unit_id), keyed by warehouse item slug.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $items = [];

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'user_car_id' => 'required|integer|exists:user_car,id',
            'warehouse' => 'required|string|exists:warehouses,slug',
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'description' => 'nullable|string',
            'selected' => ['nullable', 'array'],
            'selected.*' => ['string', 'distinct', Rule::exists('warehouse_items', 'slug')],
            'items' => ['nullable', 'array'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ];
    }

    /**
     * Keep one entry in items for every selected slug, filling in defaults
     * for newly selected ones and dropping deselected ones. Raw input is
     * left as typed so partial values survive picker-driven re-renders.
     */
    public function syncSelectedItems(): void
    {
        $lines = [];

        foreach (array_unique($this->selected) as $slug) {
            $lines[$slug] = ($this->items[$slug] ?? []) + ['quantity' => 1, 'unit_price' => 0, 'unit_id' => null];
        }

        $this->items = $lines;
        $this->fillBaseUnits();
    }

    /**
     * A native select shows its first option while the model is null, so a
     * line without a unit would display one unit and save none. Default
     * such lines to the item's base unit.
     */
    protected function fillBaseUnits(): void
    {
        $missing = array_keys(array_filter($this->items, static fn ($line) => blank($line['unit_id'] ?? null)));

        if ($missing === []) {
            return;
        }

        $baseUnits = resolve(WarehouseItemService::class)
            ->getWarehouseItems(['whereIn' => ['slug', $missing]], paginate: false)
            ->mapWithKeys(static function ($warehouseItem) {
                $units = $warehouseItem->item->unitGroup->units->where('is_active', true);

                return [$warehouseItem->slug => ($units->firstWhere('is_base', true) ?? $units->first())?->id];
            });

        foreach ($missing as $slug) {
            $this->items[$slug]['unit_id'] = $baseUnits[$slug] ?? null;
        }
    }

    public function removeOrderItem(string $slug): void
    {
        $this->selected = array_values(array_diff($this->selected, [$slug]));
        $this->syncSelectedItems();
    }

    public function lineTotal(string $slug): float
    {
        $line = $this->items[$slug] ?? [];

        return round($this->number($line['quantity'] ?? 0) * $this->number($line['unit_price'] ?? 0), 2);
    }

    protected function number(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(['٫', '٬'], ['.', ''], Utils::eDigits($value));
        }

        return is_numeric($value) ? (float) $value : 0;
    }

    public function setOrder(Order $order): void
    {
        $this->order = $order;
        $this->user_id = $order->user_id;
        $this->user_car_id = $order->user_car_id;
        $this->warehouse = $order->warehouse->slug;
        $this->status = $order->status->value;
        $this->description = $order->description;

        // The picker binds warehouse item slugs while an order stores plain
        // item ids, so map the stored lines back to their warehouse item.
        // ponytail: a line whose warehouse item was since removed is not
        // loaded, so saving the form drops it; keep it visible if that matters.
        $lines = $order->items()->get()->keyBy('item_id');
        $warehouseItems = resolve(WarehouseItemService::class)->getWarehouseItems([
            'warehouse_id' => $order->warehouse_id,
            'whereIn' => ['item_id', $lines->keys()->all()],
        ], paginate: false);

        $this->selected = [];
        $this->items = [];
        foreach ($warehouseItems as $warehouseItem) {
            $line = $lines->get($warehouseItem->item_id);
            if ($line === null) {
                continue;
            }
            $this->selected[] = $warehouseItem->slug;
            $this->items[$warehouseItem->slug] = [
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'unit_id' => $line->unit_id,
            ];
        }

        $this->fillBaseUnits();
    }

    /**
     * Display details for the selected warehouse items, keyed by slug, so
     * the line editor can label each row.
     *
     * @return array<string, array{name: string, quantity: float, units: array<int, array<string, mixed>>}>
     */
    public function selectedCatalog(): array
    {
        if ($this->selected === []) {
            return [];
        }

        return resolve(WarehouseItemService::class)
            ->getWarehouseItems(['whereIn' => ['slug', array_values($this->selected)]], paginate: false)
            ->mapWithKeys(static fn (WarehouseItem $warehouseItem) => [
                $warehouseItem->slug => [
                    'name' => $warehouseItem->item->name,
                    'quantity' => (float) $warehouseItem->quantity,
                    'units' => $warehouseItem->item->unitGroup->units
                        ->where('is_active', true)
                        ->map(fn (Unit $unit) => ['id' => $unit->id, 'name' => $unit->name, 'symbol' => $unit->symbol])
                        ->values()
                        ->all(),
                ],
            ])
            ->all();
    }

    protected function hasRecord(): bool
    {
        return $this->order !== null;
    }

    public function save(): Order
    {
        $this->useFixedWarehouse();
        $this->syncSelectedItems();
        foreach ($this->items as &$line) {
            $line['quantity'] = $this->number($line['quantity'] ?? 0);
            $line['unit_price'] = $this->number($line['unit_price'] ?? 0);
        }
        unset($line);

        $data = $this->validate();
        unset($data['selected']);

        $orderService = resolve(OrderService::class);

        try {
            if ($this->order) {
                $orderService->update($this->order, $data);

                return $this->order;
            }

            return $orderService->create($data);
        } catch (ValidationException $e) {
            // Service errors are keyed like the rules above; bind them to this form.
            throw ValidationException::withMessages(Arr::prependKeysWith($e->errors(), 'form.'));
        }
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'user_id' => __('order::attributes.User Name'),
            'user_car_id' => __('order::attributes.User Car'),
            'warehouse' => __('order::attributes.Warehouse'),
            'status' => __('order::attributes.Order Status'),
            'selected' => __('order::attributes.Warehouse Item'),
            'items.*.quantity' => __('order::attributes.Quantity'),
            'items.*.unit_price' => __('order::attributes.Unit Price'),
        ];
    }
}
