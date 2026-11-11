<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Models\OutboxEvent;
use Modules\Control\Services\ControlLink;
use Modules\Control\Services\MediaUploader;
use Modules\Control\Services\OutboxPusher;
use Modules\Control\Services\SyncEntities;
use Modules\Dashboard\Models\Media;
use Modules\Inventory\Models\Brand;

beforeEach(function () {
    Cache::flush();
    Storage::fake('public');
    config(['control.url' => 'https://control.test', 'control.token' => 'secret']);
    resolve(ControlLink::class)->markSnapshotQueued();
});

/**
 * @param  array<string, mixed>  $files  how the file endpoint answers: a response or a closure
 */
function mediaControl(mixed $files = null): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([
        '*/sync/files' => $files ?? Http::response(['data' => ['status' => 'stored']], 201),
        '*/sync/events' => fn (Request $request) => Http::response(['data' => [
            'accepted' => collect($request['events'])->map(fn (array $event, int $index) => ['index' => $index, 'event_id' => $event['event_id']])->all(),
            'already_accepted' => [],
            'rejected' => [],
        ]]),
    ]);
}

function pictureBytes(int $width, int $height, string $type = 'jpeg'): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 200, 30, 30));
    ob_start();
    $type === 'png' ? imagepng($image) : imagejpeg($image);

    return (string) ob_get_clean();
}

function picture(Brand $owner, string $bytes, string $name = 'photo.jpg'): Media
{
    $path = "inventory/brands/{$name}";
    Storage::disk('public')->put($path, $bytes);

    return Media::create([
        'model_type' => Brand::class, 'model_id' => $owner->getKey(), 'collection' => 'images', 'disk' => 'public',
        'path' => $path, 'original_name' => $name, 'mime_type' => str_ends_with($name, '.png') ? 'image/png' : 'image/jpeg', 'size' => strlen($bytes), 'order' => 0,
    ]);
}

function ownerBrand(string $name = 'Bosch'): Brand
{
    return Brand::create(['slug' => str($name)->slug()->toString(), 'name' => $name]);
}

test('a picture is announced by owner type and id, never by PHP class name', function () {
    $media = picture(ownerBrand(), pictureBytes(40, 30));

    $payload = OutboxEvent::where('aggregate_type', 'media')->sole()->payload;

    expect($payload)->toMatchArray(['id' => $media->id, 'owner_type' => 'brand', 'owner_id' => $media->model_id, 'collection' => 'images', 'original_name' => 'photo.jpg'])
        ->and($payload)->not->toHaveKeys(['model_type', 'model_id', 'path', 'disk', 'size']);
});

test('a picture of something that is not synchronized has no owner type', function () {
    $media = Media::create(['model_type' => 'App\\Models\\Elsewhere', 'model_id' => 5, 'collection' => 'images', 'disk' => 'public', 'path' => 'x.jpg']);

    expect(resolve(SyncEntities::class)->payloadOf($media)['owner_type'])->toBeNull();
});

test('the preview is a small copy sent before the event that announces the picture', function () {
    mediaControl();
    $original = pictureBytes(1600, 1200);
    $media = picture(ownerBrand(), $original);

    resolve(OutboxPusher::class)->push();

    $sent = collect(Http::recorded())->map(fn (array $pair) => $pair[0]);
    $urls = $sent->map(fn (Request $request) => basename($request->url()))->values()->all();

    // brand.created, then the preview, then both events together.
    expect($urls)->toBe(['files', 'events']);

    $upload = $sent->first();
    $file = collect($upload->data())->firstWhere('name', 'file');
    $fields = collect($upload->data())->pluck('contents', 'name');
    $size = getimagesizefromstring($file['contents']);

    // Sent as a form upload: a JSON content type here makes Jocko Control read an empty request.
    expect($upload->isMultipart())->toBeTrue()
        ->and($upload->header('Content-Type')[0])->toStartWith('multipart/form-data');

    expect($fields['media_id'])->toBe((string) $media->id)
        ->and($fields['sha256'])->toBe(hash('sha256', $file['contents']))
        ->and($size[0])->toBe(800)->and($size[1])->toBe(600)
        ->and($size['mime'])->toBe('image/jpeg')
        ->and(strlen($file['contents']))->toBeLessThan(strlen($original))
        ->and(OutboxEvent::pending()->count())->toBe(0);
});

test('small pictures are not enlarged and transparent PNGs stay PNG', function () {
    mediaControl();
    picture(ownerBrand(), pictureBytes(120, 90, 'png'), 'logo.png');

    resolve(OutboxPusher::class)->push();

    $upload = collect(Http::recorded())->map(fn (array $pair) => $pair[0])->first(fn (Request $request) => str_ends_with($request->url(), '/sync/files'));
    $size = getimagesizefromstring(collect($upload->data())->firstWhere('name', 'file')['contents']);

    expect([$size[0], $size[1], $size['mime']])->toBe([120, 90, 'image/png']);
});

test('a deleted picture sends only its event', function () {
    mediaControl();
    $media = picture(ownerBrand(), pictureBytes(40, 30));
    resolve(OutboxPusher::class)->push();
    Http::swap(new Factory);
    mediaControl();

    $media->delete();
    resolve(OutboxPusher::class)->push();

    $urls = collect(Http::recorded())->map(fn (array $pair) => basename($pair[0]->url()))->all();
    expect($urls)->toBe(['events'])
        ->and(OutboxEvent::where('event_type', 'media.deleted')->sole()->pushed_at)->not->toBeNull();
});

test('a picture whose file is gone still gets its event, just without a preview', function () {
    mediaControl();
    $media = picture(ownerBrand(), pictureBytes(40, 30));
    Storage::disk('public')->delete($media->path);

    $result = resolve(OutboxPusher::class)->push();

    expect($result['pushed'])->toBe(2)
        ->and(collect(Http::recorded())->map(fn (array $pair) => basename($pair[0]->url()))->all())->toBe(['events']);
});

test('a file Jocko Control refuses for good does not block the events behind it', function () {
    mediaControl(Http::response(['error' => ['code' => 'validation_failed', 'message' => 'The request is invalid.']], 422));
    picture(ownerBrand(), pictureBytes(40, 30));

    $result = resolve(OutboxPusher::class)->push();

    expect($result)->toBe(['pushed' => 2, 'rejected' => 0, 'remaining' => 0]);
});

test('when the file cannot be sent the events stay pending and nothing is lost', function () {
    mediaControl(Http::response(['error' => ['code' => 'server_error', 'message' => 'boom']], 500));
    picture(ownerBrand(), pictureBytes(40, 30));

    expect(fn () => resolve(OutboxPusher::class)->push())->toThrow(ControlException::class)
        ->and(OutboxEvent::pending()->count())->toBe(2);

    mediaControl();
    resolve(OutboxPusher::class)->push();

    expect(OutboxEvent::pending()->count())->toBe(0);
});

test('a preview that keeps failing on the server stops holding back the events after enough tries', function () {
    mediaControl(Http::response(['error' => ['code' => 'server_error', 'message' => 'boom']], 500));
    picture(ownerBrand(), pictureBytes(40, 30));

    OutboxEvent::query()->update(['attempts' => 20]);
    $result = resolve(OutboxPusher::class)->push();

    expect($result)->toBe(['pushed' => 2, 'rejected' => 0, 'remaining' => 0]);
});

test('a batch is cut so that it needs at most the configured number of uploads', function () {
    config(['control.files.max_per_batch' => 2]);
    $owner = ownerBrand();
    foreach (range(1, 5) as $i) {
        picture($owner, pictureBytes(40, 30), "p{$i}.jpg");
    }

    $events = OutboxEvent::query()->pending()->orderBy('id')->get();
    $limited = resolve(MediaUploader::class)->limit($events);

    // The brand's own event, then two pictures; the third picture waits for the next batch.
    expect($limited->pluck('aggregate_type')->all())->toBe(['brand', 'media', 'media']);

    mediaControl();
    $result = resolve(OutboxPusher::class)->push();

    expect($result['remaining'])->toBe(0)
        ->and(collect(Http::recorded())->filter(fn (array $pair) => str_ends_with($pair[0]->url(), '/sync/files'))->count())->toBe(5);
});

test('a picture too large to decode with the memory available is skipped', function () {
    mediaControl();
    picture(ownerBrand(), pictureBytes(2000, 2000));
    ini_set('memory_limit', (string) (memory_get_usage() + 20 * 1048576));

    try {
        resolve(OutboxPusher::class)->push();
    } finally {
        ini_set('memory_limit', '-1');
    }

    expect(collect(Http::recorded())->map(fn (array $pair) => basename($pair[0]->url()))->all())->toBe(['events']);
});

test('when Jocko Control keeps originals the picture itself is sent, not a preview', function () {
    mediaControl();
    resolve(Modules\Control\Services\PictureMode::class)->update('original');
    $original = pictureBytes(1600, 1200);
    picture(ownerBrand(), $original);

    resolve(OutboxPusher::class)->push();

    $upload = collect(Http::recorded())->map(fn (array $pair) => $pair[0])->first(fn (Request $request) => str_ends_with($request->url(), '/sync/files'));
    $file = collect($upload->data())->firstWhere('name', 'file');
    $fields = collect($upload->data())->pluck('contents', 'name');

    expect($file['contents'])->toBe($original)
        ->and($fields['sha256'])->toBe(hash('sha256', $original))
        ->and(getimagesizefromstring($file['contents'])[0])->toBe(1600);
});

test('an original that is too big is replaced by a preview instead of being dropped', function () {
    mediaControl();
    resolve(Modules\Control\Services\PictureMode::class)->update('original');
    config(['control.files.max_original_bytes' => 1000]);
    picture(ownerBrand(), pictureBytes(1600, 1200));

    resolve(OutboxPusher::class)->push();

    $upload = collect(Http::recorded())->map(fn (array $pair) => $pair[0])->first(fn (Request $request) => str_ends_with($request->url(), '/sync/files'));
    $file = collect($upload->data())->firstWhere('name', 'file');

    expect(getimagesizefromstring($file['contents'])[0])->toBe(800);
});

test('a webp original is sent with its own type', function () {
    mediaControl();
    resolve(Modules\Control\Services\PictureMode::class)->update('original');
    ob_start();
    imagewebp(imagecreatetruecolor(40, 30));
    $webp = (string) ob_get_clean();
    picture(ownerBrand(), $webp, 'photo.webp');

    resolve(OutboxPusher::class)->push();

    $upload = collect(Http::recorded())->map(fn (array $pair) => $pair[0])->first(fn (Request $request) => str_ends_with($request->url(), '/sync/files'));
    $file = collect($upload->data())->firstWhere('name', 'file');

    expect($file['filename'])->toEndWith('.webp')->and(collect((array) ($file['headers']['Content-Type'] ?? []))->first())->toBe('image/webp');
});

test('the heartbeat remembers what Jocko Control wants and resends the pictures only when that changes', function () {
    $answer = fn (string $pictures) => Http::fake(['*/device/heartbeat' => Http::response(['data' => ['received_at' => now()->toIso8601String(), 'last_sync_at' => null, 'pending_commands' => 0, 'pictures' => $pictures]])]);
    Http::swap(new Factory);
    picture(ownerBrand(), pictureBytes(40, 30));
    OutboxEvent::query()->delete();

    // Previews stay previews: nothing to resend.
    $answer('preview');
    $this->artisan('control:heartbeat')->assertSuccessful();
    expect(resolve(Modules\Control\Services\PictureMode::class)->keepsOriginals())->toBeFalse()->and(OutboxEvent::count())->toBe(0);

    // Switched to originals: every picture is queued again.
    Http::swap(new Factory);
    $answer('original');
    $this->artisan('control:heartbeat')->assertSuccessful();
    expect(resolve(Modules\Control\Services\PictureMode::class)->keepsOriginals())->toBeTrue()
        ->and(OutboxEvent::where('aggregate_type', 'media')->count())->toBe(1);

    // Still originals: not again.
    OutboxEvent::query()->delete();
    Http::swap(new Factory);
    $answer('original');
    $this->artisan('control:heartbeat')->assertSuccessful();
    expect(OutboxEvent::count())->toBe(0);
});

test('an older Jocko Control that never says keeps getting previews', function () {
    Http::swap(new Factory);
    Http::fake(['*/device/heartbeat' => Http::response(['data' => ['received_at' => now()->toIso8601String(), 'last_sync_at' => null, 'pending_commands' => 0]])]);

    $this->artisan('control:heartbeat')->assertSuccessful();

    expect(resolve(Modules\Control\Services\PictureMode::class)->keepsOriginals())->toBeFalse();
});
