<?php

namespace Modules\Inventory\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Dashboard\Models\Category;
use Modules\Dashboard\Models\Media;
use Modules\Inventory\Database\Factories\ItemFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_service
 * @property-read Category $category
 * @property-read UnitGroup $unitGroup
 *
 * @extends BaseModel<Item>
 */
#[Fillable(['name', 'slug', 'unit_group_id', 'category_id', 'description', 'is_active', 'is_service'])]
class Item extends BaseModel
{
    /**
     * @use HasFactory<ItemFactory>
     */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...parent::casts(), 'is_service' => 'boolean'];
    }

    protected static function newFactory(): ItemFactory
    {
        return ItemFactory::new();
    }

    /** @return BelongsTo<UnitGroup, $this> */
    public function unitGroup(): BelongsTo
    {
        return $this->belongsTo(UnitGroup::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return MorphMany<Media, $this> */
    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    /** @return HasManyThrough<Brand, ItemBrand, $this> */
    public function brands(): HasManyThrough
    {
        return $this->hasManyThrough(Brand::class, ItemBrand::class, 'item_id', 'id', 'id', 'brand_id');
    }
}
