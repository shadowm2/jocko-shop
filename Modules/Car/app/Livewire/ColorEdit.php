<?php

namespace Modules\Car\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Car\Livewire\Forms\ColorForm;
use Modules\Car\Models\Color;

class ColorEdit extends Component
{
    public ColorForm $form;

    public Color $color;

    public function mount(Color $color): void
    {
        $this->color = $color;
        $this->form->setColor($color);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(__('car::messages.Color Updated Successfully'), variant: 'success');
        $this->redirectRoute('colors.index', navigate: true);
    }

    public function render(): View
    {

        return view('car::livewire.color-edit', [
            'color' => $this->color,
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('car::strings.Edit Color'),
            ]);
    }
}
