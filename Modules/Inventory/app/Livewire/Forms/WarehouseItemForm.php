<?php

namespace Modules\Inventory\Livewire\Forms;

use Exception;
use Illuminate\Validation\Rule;
use Livewire\Form;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Inventory\Services\WarehouseItemService;

class WarehouseItemForm extends Form
{
    public ?int $id = null;

    public string $item = '';

    public float $quantity;

    public ?float $min_quantity;

    public bool $min_quantity_unlimited = false;

    public ?float $max_quantity;

    public bool $max_quantity_unlimited = false;

    public string $warehouse;

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        return [
            'item' => ['required', Rule::exists(Item::class, 'slug')->where('is_service', false)],
            'warehouse' => 'required|string|exists:Modules\Inventory\Models\Warehouse,slug',
            'quantity' => 'required|numeric|min:0',
            'min_quantity' => 'exclude_if:min_quantity_unlimited,true|nullable|numeric|min:0',
            'min_quantity_unlimited' => 'required|bool',
            'max_quantity' => 'exclude_if:max_quantity_unlimited,true|nullable|numeric|min:0',
            'max_quantity_unlimited' => 'required|bool',
        ];
    }

    /**
     * @throws Exception
     */
    public function save(): void
    {
        $data = $this->validate();
        $warehouseItemService = resolve(WarehouseItemService::class);

        if ($this->id) {
            $warehouseItemService->update($this->id, $data);
        } else {
            $warehouseItemService->create($data);
        }
    }

    /**
     * @throws Exception
     */
    public function setWarehouseItem(WarehouseItem $warehouseItem): void
    {
        $this->id = $warehouseItem->id;
        $this->item = $warehouseItem->item->slug ?? '';
        $this->quantity = $warehouseItem->quantity;
        $this->min_quantity = $warehouseItem->min_quantity;
        $this->max_quantity = $warehouseItem->max_quantity;
        $this->min_quantity_unlimited = is_null($warehouseItem->min_quantity);
        $this->max_quantity_unlimited = is_null($warehouseItem->max_quantity);
        $this->warehouse = $warehouseItem->warehouse->slug ?? $this->warehouse;
        //        $this->min_quantity_unlimited = false;
    }

    public function setActiveWarehouse(Warehouse $warehouse): void
    {
        $this->warehouse = $warehouse->slug;
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'item' => __('inventory::attributes.Warehouse Item'),
            'warehouse' => __('inventory::attributes.Warehouse'),
            'quantity' => __('inventory::attributes.Warehouse Item Quantity'),
        ];
    }
}
