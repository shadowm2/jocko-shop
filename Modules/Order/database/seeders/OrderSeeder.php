<?php

namespace Modules\Order\Database\Seeders;

use App\Helpers\Utils;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Modules\Car\Models\UserCar;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\User\Models\User;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Order lines are picked from warehouse stock, so Inventory seeds first.
        $warehouses = Warehouse::has('warehouseItems')->get();
        if ($warehouses->isEmpty()) {
            $this->command?->warn('No warehouse items found; seed Inventory before Order.');

            return;
        }

        $users = User::admin()
            ->get();

        $userCars = UserCar::with('car')->get();
        $faker = Factory::create();

        foreach ($users as $user) {
            $currentUserCars = $userCars->filter(fn ($userCar) => $userCar->user_id == $user->id);
            foreach ($currentUserCars as $userCar) {
                if ($faker->boolean(90)) {
                    continue;
                }
                $slug = Utils::generateUniqueSlug($user->initials().' '.$userCar->car->name, Order::class);
                $warehouse = $warehouses->random();
                $order = Order::factory()
                    ->for($user)
                    ->for($userCar)
                    ->for($warehouse)
                    ->create([
                        'slug' => $slug,
                    ]);

                $warehouse->warehouseItems()
                    ->inRandomOrder()
                    ->limit($faker->numberBetween(1, 4))
                    ->get()
                    ->each(fn (WarehouseItem $warehouseItem) => OrderItem::factory()
                        ->for($order)
                        ->create([
                            'item_id' => $warehouseItem->item_id,
                            'unit_id' => $warehouseItem->item->unitGroup->units->firstWhere('is_base', true)?->id,
                        ]));
            }
        }
    }
}
