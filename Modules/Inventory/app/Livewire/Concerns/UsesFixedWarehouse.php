<?php

namespace Modules\Inventory\Livewire\Concerns;

use Modules\Inventory\Services\WarehouseService;

/**
 * For forms that carry a `warehouse` slug: while the shop works with a single
 * warehouse nobody picks one, the form just uses it.
 */
trait UsesFixedWarehouse
{
    /**
     * Whether the form edits a record that already exists (it then keeps the
     * warehouse it was made in).
     */
    abstract protected function hasRecord(): bool;

    /**
     * Fill in the one warehouse in use. Does nothing when the shop works with
     * several, or for a record that already has its own.
     */
    public function useFixedWarehouse(): void
    {
        $fixed = resolve(WarehouseService::class)->fixedWarehouse();

        if ($fixed !== null && ! $this->hasRecord()) {
            $this->warehouse = $fixed->slug;
        }
    }

    /**
     * The picker is only needed when there is a choice, or when there is no
     * warehouse to use yet (the item picker cannot work without one).
     */
    public function showsWarehousePicker(): bool
    {
        return resolve(WarehouseService::class)->isMultiple() || blank($this->warehouse);
    }
}
