<?php

use Livewire\Component;
use Modules\Car\Livewire\Forms\CarForm;

it('refuses previous images that are not media', function () {
    $form = new CarForm(new class extends Component {}, 'form');

    expect(fn () => $form->setPreviousImages(collect(['not media'])))
        ->toThrow(InvalidArgumentException::class, 'previous images should be an instance of Media');

    $form->setPreviousImages(null);
    expect($form->prev_images)->toBe([]);
});

it('accepts pictures up to the size Livewire itself allows, not just 2 MB', function () {
    $rules = (new CarForm(new class extends Component {}, 'form'))->getImageRules();
    $check = fn (int $kilobytes) => Illuminate\Support\Facades\Validator::make(
        ['images' => [Illuminate\Http\UploadedFile::fake()->image('photo.jpg')->size($kilobytes)]],
        ['images' => $rules['images'], 'images.*' => $rules['images.*']],
    )->passes();

    expect($check(1500))->toBeTrue()
        ->and($check(6000))->toBeTrue('a phone photo')
        ->and($check(12000))->toBeTrue()
        ->and($check(13000))->toBeFalse('over what Livewire accepts');

    expect(Modules\Dashboard\Livewire\Forms\ImageForm::MAX_KILOBYTES)->toBe(12288);
});
