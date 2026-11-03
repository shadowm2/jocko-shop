<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Car\Models\CarCompany;
use Modules\Dashboard\Services\FileUploadService;

it('stores uploads on the public disk even when the default disk is private', function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('public');
    Storage::fake('local');

    $media = resolve(FileUploadService::class)->upload(
        file: UploadedFile::fake()->image('logo.png'),
        module: 'Car',
        model: CarCompany::factory()->create(),
    );

    expect($media->disk)->toBe('public');
    Storage::disk('public')->assertExists($media->path);
    Storage::disk('local')->assertMissing($media->path);
});

it('links images with a relative url so the host and port of APP_URL do not matter', function () {
    config(['app.url' => 'http://localhost']);

    expect(config('filesystems.disks.public.url'))->toBe('/storage')
        ->and(Storage::disk('public')->url('car/cars/a.jpg'))->toBe('/storage/car/cars/a.jpg');
});
