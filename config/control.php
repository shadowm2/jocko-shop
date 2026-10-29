<?php

use Modules\Car\Models\Car;
use Modules\Car\Models\CarCompany;
use Modules\Car\Models\Color;
use Modules\Car\Models\UserCar;
use Modules\Dashboard\Models\Category;
use Modules\Dashboard\Models\City;
use Modules\Dashboard\Models\Country;
use Modules\Dashboard\Models\Province;
use Modules\Inventory\Models\Brand;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemBrand;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\PurchaseItem;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\User\Models\User;

return [
    /*
    | Where this installation reports to (Jocko Control). The module does
    | nothing unless both are set, so the shop runs fine on its own.
    | The token is a secret: keep it in .env only, never in source control.
    */
    'url' => env('JOCKO_CONTROL_URL'),
    'token' => env('JOCKO_CONTROL_TOKEN'),

    'timeout' => (int) env('JOCKO_CONTROL_TIMEOUT', 10),
    'connect_timeout' => 5,

    // Shown in Jocko Control. Defaults to the Composer root package version.
    'app_version' => env('APP_VERSION'),

    'commands' => [
        'max_per_run' => 20,
    ],

    /*
    | Business data sent to Jocko Control so administrators can browse a
    | read-only copy of this shop. Every create, update, delete and restore of
    | a listed model is recorded in the outbox (in the same transaction as the
    | change) and pushed by `control:push`.
    |
    | `fields` is an ALLOWLIST. Anything not listed is never sent, which is how
    | passwords, OTP codes, tokens and settings stay on this machine. id,
    | created_at, updated_at and deleted_at are always included. Never add a
    | credential here. Keep the entity keys and fields in step with
    | config/shop_data.php of jocko-control.
    */
    'sync' => [
        'batch_size' => 100,
        'max_batches_per_run' => 20,
        'retention_days' => 7,

        'entities' => [
            Order::class => ['type' => 'order', 'fields' => ['slug', 'user_id', 'user_car_id', 'warehouse_id', 'order_number', 'status', 'description']],
            OrderItem::class => ['type' => 'order_item', 'fields' => ['slug', 'order_id', 'item_id', 'unit_id', 'quantity', 'unit_price', 'total']],
            Payment::class => ['type' => 'payment', 'fields' => ['slug', 'order_id', 'amount', 'method', 'paid_at', 'description']],
            User::class => ['type' => 'user', 'fields' => ['slug', 'type', 'first_name', 'last_name', 'email', 'mobile']],
            UserCar::class => ['type' => 'user_car', 'fields' => ['slug', 'user_id', 'car_id', 'color_id', 'manufactured_at', 'description']],
            CarCompany::class => ['type' => 'car_company', 'fields' => ['slug', 'name']],
            Car::class => ['type' => 'car', 'fields' => ['slug', 'car_company_id', 'name']],
            Color::class => ['type' => 'color', 'fields' => ['slug', 'name', 'title', 'hex']],
            Item::class => ['type' => 'item', 'fields' => ['category_id', 'unit_group_id', 'name', 'slug', 'description', 'is_active']],
            ItemBrand::class => ['type' => 'item_brand', 'fields' => ['slug', 'item_id', 'brand_id', 'sku', 'barcode', 'part_number', 'purchase_price', 'sale_price', 'is_active']],
            Brand::class => ['type' => 'brand', 'fields' => ['slug', 'name', 'description', 'is_active']],
            Category::class => ['type' => 'category', 'fields' => ['parent_id', 'name', 'slug', 'type', 'depth', 'description', 'icon', 'is_active', 'sort_order']],
            UnitGroup::class => ['type' => 'unit_group', 'fields' => ['slug', 'name']],
            Unit::class => ['type' => 'unit', 'fields' => ['slug', 'name', 'symbol', 'unit_group_id', 'conversion_factor', 'is_base', 'is_active']],
            Warehouse::class => ['type' => 'warehouse', 'fields' => ['slug', 'name', 'description', 'is_active']],
            WarehouseItem::class => ['type' => 'warehouse_item', 'fields' => ['slug', 'warehouse_id', 'item_id', 'quantity', 'min_quantity', 'max_quantity']],
            StockMovement::class => ['type' => 'stock_movement', 'fields' => ['slug', 'item_id', 'warehouse_item_id', 'user_id', 'from_warehouse_id', 'to_warehouse_id', 'quantity', 'unit_cost', 'total_cost', 'balance_after', 'type', 'reference_type', 'reference_id', 'description']],
            Supplier::class => ['type' => 'supplier', 'fields' => ['slug', 'user_id', 'phone', 'city_id', 'address', 'postal_code', 'website', 'tax_number', 'is_active', 'notes']],
            Purchase::class => ['type' => 'purchase', 'fields' => ['slug', 'supplier_id', 'warehouse_id', 'order_number', 'status', 'ordered_at', 'expected_at', 'received_at', 'notes', 'created_by']],
            PurchaseItem::class => ['type' => 'purchase_item', 'fields' => ['slug', 'purchase_id', 'item_id', 'unit_id', 'quantity', 'received_quantity', 'unit_price', 'discount', 'tax', 'total']],
            Country::class => ['type' => 'country', 'fields' => ['name', 'code', 'code3', 'phone_code', 'is_active']],
            Province::class => ['type' => 'province', 'fields' => ['country_id', 'name', 'slug', 'is_active']],
            City::class => ['type' => 'city', 'fields' => ['province_id', 'name', 'slug', 'is_active']],
        ],
    ],

    'diagnostics' => [
        'max_log_lines' => 50,
        'max_log_line_length' => 300,
        'log_tail_bytes' => 262144,
    ],
];
