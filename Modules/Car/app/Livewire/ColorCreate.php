<?php

namespace Modules\Car\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Car\Livewire\Forms\ColorForm;

class ColorCreate extends Component
{
    public ColorForm $form;

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('car::messages.Color Created Successfully'), variant: 'success');
        $this->redirectRoute('colors.index', navigate: true);
    }

    public function render(): View
    {
        return view('car::livewire.color-add', [
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('car::strings.Add Color'),
            ]);
    }
}
