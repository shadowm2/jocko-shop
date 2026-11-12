<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Control\Models\OutboxEvent;
use Modules\Control\Services\ControlLink;
use Modules\Control\Services\DeviceStatus;
use Modules\Control\Services\RecordCounts;
use Modules\Inventory\Models\Brand;
use Modules\Inventory\Models\UnitGroup;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
    deferForeignKeys();
    config(['control.url' => 'https://control.test', 'control.token' => 'secret']);
    resolve(ControlLink::class)->markSnapshotQueued();
});

it('counts the records of every synchronized type and how many are deleted', function () {
    Brand::create(['slug' => 'bosch', 'name' => 'Bosch']);
    Brand::create(['slug' => 'mann', 'name' => 'Mann'])->delete();
    UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);

    $counts = resolve(RecordCounts::class)->all();

    expect($counts['brand'])->toBe(['alive' => 1, 'removed' => 1])
        ->and($counts['unit_group'])->toBe(['alive' => 1, 'removed' => 0])
        ->and($counts['order'])->toBe(['alive' => 0, 'removed' => 0]);
});

it('adds the counts to the heartbeat once the shop has sent everything', function () {
    Brand::create(['slug' => 'bosch', 'name' => 'Bosch']);
    OutboxEvent::query()->update(['pushed_at' => now()]);

    $payload = resolve(DeviceStatus::class)->heartbeatPayload();

    expect($payload['record_counts']['brand'])->toBe(['alive' => 1, 'removed' => 0]);
});

it('leaves the counts out while changes are still waiting to be sent', function () {
    Brand::create(['slug' => 'bosch', 'name' => 'Bosch']);

    expect(resolve(DeviceStatus::class)->heartbeatPayload())->not->toHaveKey('record_counts');
});

it('reports the counts at most every half hour', function () {
    expect(resolve(DeviceStatus::class)->heartbeatPayload())->toHaveKey('record_counts')
        ->and(resolve(DeviceStatus::class)->heartbeatPayload())->not->toHaveKey('record_counts');

    $this->travel(RecordCounts::EVERY_MINUTES + 1)->minutes();

    expect(resolve(DeviceStatus::class)->heartbeatPayload())->toHaveKey('record_counts');
});

it('resends only the entity types Control names', function () {
    Http::fake([
        '*/sync/events' => fn (Request $request) => Http::response(['data' => [
            'accepted' => collect($request['events'])->map(fn (array $event, int $index) => ['index' => $index, 'event_id' => $event['event_id']])->all(),
            'already_accepted' => [], 'rejected' => [],
        ]]),
        '*/device/commands*' => Http::response(['data' => [['id' => '22222222-2222-4222-8222-222222222222', 'type' => 'sync_now', 'payload' => ['entities' => ['brand']]]]]),
        '*/ack' => Http::response(['data' => ['status' => 'completed', 'already_acknowledged' => false]]),
    ]);
    Brand::create(['slug' => 'bosch', 'name' => 'Bosch']);
    UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    OutboxEvent::query()->update(['pushed_at' => now()]);

    $this->artisan('control:poll')->assertSuccessful();

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/sync/events')
        && collect($r['events'])->every(fn (array $event) => str_starts_with($event['event_type'], 'brand.'))
        && collect($r['events'])->contains(fn (array $event) => $event['event_type'] === 'brand.snapshot'));
});
