<?php

namespace Modules\Inventory\Livewire\Forms;

use Illuminate\Validation\Rule;
use Modules\Dashboard\Enums\CategoryType;
use Modules\Dashboard\Livewire\Forms\ImageForm;
use Modules\Dashboard\Models\Category;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Services\ItemService;

class ItemForm extends ImageForm
{
    public ?Item $item = null;

    public ?int $id = null;

    public string $name = '';

    public ?string $category;

    public string $unit_group = '';

    public bool $is_active = true;

    public bool $is_service = false;

    public string $description = '';

    /** @return array<string, mixed> */
    public function getRules(): array
    {
        $rules = [
            'name' => 'required',
            'description' => 'nullable|string',
            'category' => ['required', 'string',
                Rule::exists(Category::class, new Category()->getRouteKeyName())->where(function ($query) {
                    $query->where('type', CategoryType::Item->value);
                }),
            ],
            'unit_group' => ['required', 'string', 'exists:Modules\Inventory\Models\UnitGroup,'.new UnitGroup()->getRouteKeyName()],
            'is_active' => 'nullable|boolean',
            'is_service' => 'nullable|boolean',
            ...$this->getImageRules($this->item?->images),
        ];

        return $rules;
    }

    public function save(): void
    {
        $data = $this->validate();

        $itemService = resolve(ItemService::class);

        if ($this->id) {
            $itemService->update($this->id, $data);
        } else {
            $itemService->create($data);
        }
    }

    public function setItem(Item $item): void
    {
        $this->item = $item;
        $this->id = $item->id;
        $this->name = $item->name;
        $this->description = $item->description ?? '';
        $this->category = $item->category->slug;
        $this->unit_group = $item->unitGroup->slug;
        $this->is_active = $item->is_active;
        $this->is_service = $item->is_service;
        $this->setPreviousImages($item->images);
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'name' => __('inventory::attributes.Item Name'),
            'description' => __('inventory::attributes.Item Description'),
            'is_active' => __('inventory::attributes.Is Active'),
            'is_service' => __('inventory::attributes.Is Service'),
            'logo' => __('inventory::attributes.Item Logo'),
            'category' => __('inventory::attributes.Item Category'),
        ];
    }
}
