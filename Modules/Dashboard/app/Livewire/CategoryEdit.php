<?php

namespace Modules\Dashboard\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Dashboard\Enums\CategoryType;
use Modules\Dashboard\Livewire\Forms\CategoryForm;
use Modules\Dashboard\Models\Category;
use Modules\Dashboard\Services\CategoryService;

class CategoryEdit extends Component
{
    public CategoryForm $form;

    public Category $category;

    public function mount(): void
    {
        $this->form->setCategory($this->category);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('dashboard::messages.Category Updated Successfully'), variant: 'success');
        $this->redirectRoute('categories.index', navigate: true);
    }

    public function render(): View
    {
        $categoryService = resolve(CategoryService::class);
        $children = $this->category->getChildrenRecursive();
        $children = $children->pluck('id')->push($this->category->id)->toArray();
        $categories = $categoryService->getCategories(filters: [
            'whereNotIn' => ['id', $children],
        ], paginate: false);
        $types = CategoryType::cases();

        return view('dashboard::livewire.categories-edit', [
            ...compact('categories', 'types'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('dashboard::strings.Edit Category'),
            ]);
    }
}
