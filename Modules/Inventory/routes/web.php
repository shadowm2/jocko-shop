<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Livewire\Brands\BrandCreate;
use Modules\Inventory\Livewire\Brands\BrandEdit;
use Modules\Inventory\Livewire\Brands\BrandList;
use Modules\Inventory\Livewire\ItemBrands\ItemBrandCreate;
use Modules\Inventory\Livewire\ItemBrands\ItemBrandEdit;
use Modules\Inventory\Livewire\ItemBrands\ItemBrandList;
use Modules\Inventory\Livewire\Items\ItemCreate;
use Modules\Inventory\Livewire\Items\ItemEdit;
use Modules\Inventory\Livewire\Items\ItemList;
use Modules\Inventory\Livewire\Purchases\PurchaseCreate;
use Modules\Inventory\Livewire\Purchases\PurchaseEdit;
use Modules\Inventory\Livewire\Purchases\PurchaseList;
use Modules\Inventory\Livewire\Purchases\ReorderList;
use Modules\Inventory\Livewire\StockMovements\StockAdjustmentCreate;
use Modules\Inventory\Livewire\StockMovements\StockMovementList;
use Modules\Inventory\Livewire\StockMovements\StockTransferCreate;
use Modules\Inventory\Livewire\Suppliers\SupplierCreate;
use Modules\Inventory\Livewire\Suppliers\SupplierEdit;
use Modules\Inventory\Livewire\Suppliers\SupplierList;
use Modules\Inventory\Livewire\UnitGroups\UnitGroupCreate;
use Modules\Inventory\Livewire\UnitGroups\UnitGroupEdit;
use Modules\Inventory\Livewire\UnitGroups\UnitGroupList;
use Modules\Inventory\Livewire\Units\UnitCreate;
use Modules\Inventory\Livewire\Units\UnitEdit;
use Modules\Inventory\Livewire\Units\UnitList;
use Modules\Inventory\Livewire\WarehouseItems\LowStockList;
use Modules\Inventory\Livewire\WarehouseItems\WarehouseItemCreate;
use Modules\Inventory\Livewire\WarehouseItems\WarehouseItemEdit;
use Modules\Inventory\Livewire\WarehouseItems\WarehouseItemList;
use Modules\Inventory\Livewire\Warehouses\WarehouseCreate;
use Modules\Inventory\Livewire\Warehouses\WarehouseEdit;
use Modules\Inventory\Livewire\Warehouses\WarehouseList;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('units')->as('units.')->group(function () {
        Route::livewire('/list', UnitList::class)->name('index');
        Route::livewire('/new', UnitCreate::class)->name('create');
        Route::livewire('/{unit}/edit', UnitEdit::class)->name('edit');

        Route::prefix('groups')->as('groups.')->group(function () {
            Route::livewire('/list', UnitGroupList::class)->name('index');
            Route::livewire('/new', UnitGroupCreate::class)->name('create');
            Route::livewire('/{unitGroup}/edit', UnitGroupEdit::class)->name('edit');
        });
    });

    Route::prefix('purchases')->middleware('can:purchasing.manage')->as('purchases.')->group(function () {
        Route::livewire('/list', PurchaseList::class)->name('index');
        Route::livewire('/new', PurchaseCreate::class)->name('create');
        Route::livewire('/reorder', ReorderList::class)->name('reorder');
        Route::livewire('/{purchase}/edit', PurchaseEdit::class)->name('edit');
    });

    Route::prefix('suppliers')->middleware('can:purchasing.manage')->as('suppliers.')->group(function () {
        Route::livewire('/list', SupplierList::class)->name('index');
        Route::livewire('/new', SupplierCreate::class)->name('create');
        Route::livewire('/{supplier}/edit', SupplierEdit::class)->name('edit');
    });

    Route::prefix('brands')->as('brands.')->group(function () {
        Route::livewire('/list', BrandList::class)->name('index');
        Route::livewire('/new', BrandCreate::class)->name('create');
        Route::livewire('/{brand}/edit', BrandEdit::class)->name('edit');
    });

    Route::prefix('items')->as('items.')->group(function () {
        Route::livewire('/list', ItemList::class)->name('index');
        Route::livewire('/new', ItemCreate::class)->middleware('can:items.manage')->name('create');
        Route::livewire('/{item}/edit', ItemEdit::class)->middleware('can:items.manage')->name('edit');

        Route::prefix('{item}/brands')->as('brands.')->group(function () {
            Route::livewire('/list', ItemBrandList::class)->name('index');
            Route::livewire('/new', ItemBrandCreate::class)->name('create');
            Route::livewire('/{itemBrand}/edit', ItemBrandEdit::class)->name('edit');
        });
    });

    Route::prefix('stock-movements')->as('stock-movements.')->group(function () {
        Route::livewire('/list', StockMovementList::class)->name('index');
        Route::livewire('/new', StockAdjustmentCreate::class)->middleware('can:stock.manage')->name('create');
        Route::livewire('/transfer', StockTransferCreate::class)->middleware('can:stock.manage')->name('transfer');
    });

    Route::prefix('warehouses')->as('warehouses.')->group(function () {
        Route::livewire('/list', WarehouseList::class)->name('index');
        Route::livewire('/new', WarehouseCreate::class)->name('create');
        Route::livewire('/{warehouse}/edit', WarehouseEdit::class)->name('edit');

        Route::prefix('items')->as('items.')->group(function () {
            Route::livewire('/list', WarehouseItemList::class)->name('index');
            Route::livewire('/low-stock', LowStockList::class)->name('low-stock');
            Route::livewire('/{warehouse}/new', WarehouseItemCreate::class)->name('create');
            Route::livewire('/{warehouse}/{warehouseItem}/edit', WarehouseItemEdit::class)->name('edit');
        });
    });
});
