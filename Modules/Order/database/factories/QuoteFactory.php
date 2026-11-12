<?php

namespace Modules\Order\Database\Factories;

use App\Helpers\Utils;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Enums\QuoteStatus;
use Modules\Order\Models\Quote;

/** @extends Factory<Quote> */
class QuoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Quote::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $number = Quote::nextNumber();

        return [
            'slug' => Utils::generateUniqueSlug($number, Quote::class),
            'quote_number' => $number,
            'warehouse_id' => Warehouse::query()->inRandomOrder()->value('id'),
            'status' => QuoteStatus::DRAFT,
            'valid_until' => now()->addDays(7)->toDateString(),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
