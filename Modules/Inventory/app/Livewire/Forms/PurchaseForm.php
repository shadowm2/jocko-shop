<?php

namespace Modules\Inventory\Livewire\Forms;

use App\Helpers\Utils;
use App\Rules\JalaliDate;
use Exception;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Form;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Livewire\Concerns\UsesFixedWarehouse;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Inventory\Services\PurchaseService;
use Modules\Inventory\Services\WarehouseItemService;
use Morilog\Jalali\Jalalian;

class PurchaseForm extends Form
{
    use UsesFixedWarehouse;

    public ?int $id = null;

    public ?Purchase $purchase = null;

    public string $warehouse = '';

    public string $supplier = '';

    public string $order_number = '';

    public string $ordered_at = '';

    public string $ordered_at_gregorian = '';

    public string $expected_at = '';

    public string $expected_at_gregorian = '';

    public string $received_at = '';

    public string $received_at_gregorian = '';

    public ?string $notes = '';

    /**
     * Warehouse item slugs currently chosen in the picker.
     *
     * @var array<int, string>
     */
    public array $selected = [];

    /**
     * Per line values, keyed by warehouse item slug.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $items = [];

    /**
     * Keep one entry in items for every selected slug, filling in
     * defaults for newly selected ones and dropping deselected ones so
     * the validation rules below always see a complete set.
     */
    protected function syncLineValues(): void
    {
        $lines = [];

        foreach (array_unique($this->selected) as $slug) {
            $line = array_merge([
                'quantity' => 1,
                'unit_price' => 0,
                'discount' => 0,
                'tax' => 0,
                'total' => 0,
                'unit_id' => null,
            ], $this->items[$slug] ?? []);

            // Preserve raw input while editing so empty or partial values do
            // not get coerced to zero during picker-driven re-renders.
            // Total is always derived, never taken from the request.
            $line['total'] = round(
                $this->numericInputValue($line['quantity']) * $this->numericInputValue($line['unit_price']),
                2,
            );

            $lines[$slug] = $line;
        }

        $this->items = $lines;
    }

    public function syncSelectedItems(): void
    {
        $this->syncLineValues();
    }

    public function lineTotal(string $slug): float
    {
        $line = $this->items[$slug] ?? [];

        return round(
            $this->numericInputValue($line['quantity'] ?? 0) * $this->numericInputValue($line['unit_price'] ?? 0),
            2,
        );
    }

    protected function numericInputValue(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(['٫', '٬'], ['.', ''], Utils::eDigits($value));
        }

        return is_numeric($value) ? (float) $value : 0;
    }

    protected function normalizeLineInputDigits(): void
    {
        foreach ($this->items as &$line) {
            foreach (['quantity', 'unit_price', 'discount', 'tax'] as $field) {
                if (is_string($line[$field] ?? null)) {
                    $line[$field] = str_replace(['٫', '٬'], ['.', ''], Utils::eDigits($line[$field]));
                }
            }
        }

        unset($line);
    }

    /**
     * A purchase being created has not been stored yet, so it reports the
     * display-only NEW status rather than the default that create() will
     * actually apply.
     */
    public function currentStatus(): PurchaseStatus
    {
        return $this->purchase->status ?? PurchaseStatus::NEW;
    }

    public function isUnsaved(): bool
    {
        return $this->purchase === null;
    }

    public function statusLabel(): string
    {
        return $this->currentStatus()->label();
    }

    public function statusColor(): string
    {
        return $this->currentStatus()->color();
    }

    /** @return array<string, mixed> */
    public function getRules(): array
    {
        return [
            'warehouse' => 'required|string|exists:Modules\Inventory\Models\Warehouse,slug',
            'supplier' => 'required|string|exists:Modules\Inventory\Models\Supplier,slug',
            'order_number' => [
                'required',
                'string',
                Rule::unique(Purchase::class, 'order_number')
                    ->ignore($this->purchase?->id),
            ],
            'ordered_at' => ['required', 'string', new JalaliDate],
            'expected_at' => ['required', 'string', new JalaliDate],
            'received_at' => ['required', 'string', new JalaliDate],
            'notes' => 'nullable|string',
            'selected' => ['nullable', 'array'],
            'selected.*' => [
                'string',
                'distinct',
                Rule::exists('warehouse_items', 'slug'),
            ],
            'items' => ['nullable', 'array'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:Modules\Inventory\Models\Unit,id'],
            'items.*.total' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @throws Exception
     */
    protected function hasRecord(): bool
    {
        return $this->purchase !== null;
    }

    public function save(): bool
    {
        $this->useFixedWarehouse();
        $this->normalizeLineInputDigits();
        $this->syncLineValues();

        $data = $this->validate();
        $purchaseService = resolve(PurchaseService::class);
        $data['ordered_at'] = Jalalian::fromFormat('Y/m/d', Utils::eDigits($data['ordered_at']))->toCarbon();
        $data['expected_at'] = Jalalian::fromFormat('Y/m/d', Utils::eDigits($data['expected_at']))->toCarbon();
        $data['received_at'] = Jalalian::fromFormat('Y/m/d', Utils::eDigits($data['received_at']))->toCarbon();

        unset($data['selected']);

        try {
            if ($this->purchase) {
                $purchaseService->update($this->purchase->id, $data);
            } else {
                $purchaseService->create($data);
            }
        } catch (\Throwable $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return false;
        }

        return true;
    }

    /** Save only the purchase lines from the list's item editor. */
    public function saveItems(): bool
    {
        $purchase = $this->purchase;
        if (! $purchase) {
            throw new Exception('A saved purchase is required to update its items.');
        }

        $this->normalizeLineInputDigits();
        $this->syncLineValues();
        $rules = $this->getRules();
        $data = $this->validate([
            'selected' => $rules['selected'],
            'selected.*' => $rules['selected.*'],
            'items' => $rules['items'],
            'items.*.quantity' => $rules['items.*.quantity'],
            'items.*.unit_price' => $rules['items.*.unit_price'],
            'items.*.unit_id' => $rules['items.*.unit_id'],
            'items.*.total' => $rules['items.*.total'],
        ]);

        try {
            resolve(PurchaseService::class)->update($purchase->id, [
                'items' => $data['items'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return false;
        }

        $purchase->refresh();

        return true;
    }

    public function removePurchaseItem(string $slug): void
    {
        $this->selected = array_values(array_filter(
            $this->selected,
            static fn (string $selectedSlug): bool => $selectedSlug !== $slug,
        ));
        $this->syncSelectedItems();
    }

    /**
     * Display details for the currently selected warehouse items, keyed by
     * slug, so the line editor can label each row.
     *
     * @return array<string, array{name: string, quantity: float}>
     */
    public function selectedCatalog(): array
    {
        $slugs = array_values(array_filter($this->selected));

        if (empty($slugs)) {
            return [];
        }

        return resolve(WarehouseItemService::class)
            ->getWarehouseItems(['whereIn' => ['slug', $slugs]], paginate: false)
            ->mapWithKeys(static fn (WarehouseItem $warehouseItem) => [
                $warehouseItem->slug => [
                    'name' => $warehouseItem->item->name,
                    'quantity' => (float) $warehouseItem->quantity,
                    'units' => $warehouseItem->item->unitGroup->units
                        ->where('is_active', true)
                        ->values()
                        ->map(fn (Unit $unit) => ['id' => $unit->id, 'name' => $unit->name, 'symbol' => $unit->symbol])
                        ->all(),
                ],
            ])
            ->all();
    }

    /**
     * @throws Exception
     */
    public function setPurchase(Purchase $purchase): void
    {
        $this->purchase = $purchase;
        $this->warehouse = $purchase->warehouse->slug;
        $this->supplier = $purchase->supplier->slug;
        $this->order_number = $purchase->order_number;
        $this->ordered_at = Utils::pDigits($purchase->ordered_at_jalali?->format('Y/m/d') ?? '') ?? '';
        $this->ordered_at_gregorian = $purchase->ordered_at?->format('Y/m/d') ?? '';
        $this->expected_at = Utils::pDigits($purchase->expected_at_jalali?->format('Y/m/d') ?? '') ?? '';
        $this->expected_at_gregorian = $purchase->expected_at?->format('Y/m/d') ?? '';
        $this->received_at = Utils::pDigits($purchase->received_at_jalali?->format('Y/m/d') ?? '') ?? '';
        $this->received_at_gregorian = $purchase->received_at?->format('Y/m/d') ?? '';
        $this->notes = $purchase->notes;

        $this->selected = $this->resolveWarehouseItemSlugs($purchase);
        $this->items = $this->resolveLineValues($purchase);
    }

    /**
     * The picker binds warehouse item slugs, while a purchase stores plain
     * item ids, so map the stored lines back to their warehouse item.
     *
     * @return array<int, string>
     */
    protected function resolveWarehouseItemSlugs(Purchase $purchase): array
    {
        $itemIds = $purchase->items()
            ->pluck('item_id')
            ->all();

        if (empty($itemIds)) {
            return [];
        }

        return resolve(WarehouseItemService::class)
            ->getWarehouseItems([
                'warehouse_id' => $purchase->warehouse_id,
                'whereIn' => ['item_id', $itemIds],
            ], paginate: false)
            ->pluck('slug')
            ->all();
    }

    /**
     * Carry the stored quantities and prices back into the form so editing
     * a purchase does not reset them.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function resolveLineValues(Purchase $purchase): array
    {
        $slugs = $this->resolveWarehouseItemSlugs($purchase);

        if (empty($slugs)) {
            return [];
        }

        $warehouseItems = resolve(WarehouseItemService::class)
            ->getWarehouseItems([
                'warehouse_id' => $purchase->warehouse_id,
                'whereIn' => ['item_id', $purchase->items()->pluck('item_id')->all()],
            ], paginate: false)
            ->keyBy('item_id');

        $lines = [];

        foreach ($purchase->items()->get() as $line) {
            $warehouseItem = $warehouseItems->get($line->item_id);

            if (! $warehouseItem || ! in_array($warehouseItem->slug, $slugs, true)) {
                continue;
            }

            $lines[$warehouseItem->slug] = [
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'unit_id' => $line->unit_id,
                'discount' => (float) $line->discount,
                'tax' => (float) $line->tax,
                'total' => (float) $line->total,
            ];
        }

        return $lines;
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'warehouse' => __('inventory::attributes.Purchase Warehouse'),
            'supplier' => __('inventory::attributes.Purchase Supplier'),
            'order_number' => __('inventory::attributes.Purchase Order Number'),
            'ordered_at' => __('inventory::attributes.Purchase Ordered At'),
            'expected_at' => __('inventory::attributes.Purchase Expected At'),
            'received_at' => __('inventory::attributes.Purchase Received At'),
            'selected' => __('inventory::attributes.Warehouse Item'),
            'items.*.quantity' => __('inventory::attributes.Purchase Quantity'),
            'items.*.unit_price' => __('inventory::attributes.Purchase Unit Price'),
            'items.*.total' => __('inventory::attributes.Purchase Total'),
        ];
    }
}
