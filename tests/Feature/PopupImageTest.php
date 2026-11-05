<?php

use Illuminate\Support\Facades\Blade;
use Modules\Dashboard\Models\Media;

it('titles the image preview with the label it was given', function (string $label) {
    $media = new Media(['disk' => 'public', 'path' => 'x.png']);

    $html = Blade::render(
        '<x-dashboard::popup-image :image="$media" name="Bosch" :label="$label" />',
        ['media' => $media, 'label' => $label],
    );

    expect($html)->toContain($label.':')
        ->and($html)->not->toContain('Company name');
})->with(['Brand name', 'Item name', 'Car name']);
