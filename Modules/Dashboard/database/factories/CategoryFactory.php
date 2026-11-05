<?php

namespace Modules\Dashboard\Database\Factories;

use App\Helpers\Utils;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Dashboard\Enums\CategoryType;
use Modules\Dashboard\Models\Category;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        /** @var string $name */
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Utils::generateUniqueSlug($name, Category::class),
            'parent_id' => null,
            'type' => $this->faker->randomElement(CategoryType::cases()),
            'icon' => null,
            'description' => $this->faker->optional()->sentence(),
            'sort_order' => 0,
            'depth' => 0,
            'is_active' => true,
        ];
    }
}
