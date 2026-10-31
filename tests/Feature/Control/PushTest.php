<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Models\OutboxEvent;
use Modules\Control\Services\ControlLink;
use Modules\Control\Services\DeviceStatus;
use Modules\Control\Services\OutboxPusher;
use Modules\Control\Services\OutboxSnapshot;
use Modules\Inventory\Models\Brand;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
    config(['control.url' => 'https://control.test', 'control.token' => 'secret']);
    // Pretend the first full copy was queued long ago; tests that care unset it.
    resolve(ControlLink::class)->markSnapshotQueued();
});

/**
 * Http::fake() only ever adds stubs and the first match wins, so start clean.
 *
 * @param  array<string, mixed>  $stubs
 */
function fakeHttp(array $stubs): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake($stubs);
}

/**
 * Answer a push the way Jocko Control does, per event.
 *
 * @param  array<string, 'accepted'|'already_accepted'|array{code: string, message: string}>  $outcomes  event_id => outcome; anything else is accepted
 */
function fakePush(array $outcomes = []): void
{
    fakeHttp(['*/sync/events' => function (Request $request) use ($outcomes) {
        $data = ['accepted' => [], 'already_accepted' => [], 'rejected' => []];

        foreach ($request['events'] as $index => $event) {
            $outcome = $outcomes[$event['event_id']] ?? 'accepted';

            if (is_array($outcome)) {
                $data['rejected'][] = ['index' => $index, 'event_id' => $event['event_id']] + $outcome;
            } else {
                $data[$outcome][] = ['index' => $index, 'event_id' => $event['event_id']];
            }
        }

        return Http::response(['data' => $data]);
    }]);
}

function brand(string $name): Brand
{
    return Brand::create(['slug' => str($name)->slug()->toString(), 'name' => $name]);
}

test('events are sent oldest first in the wire format Jocko Control expects, then marked as pushed', function () {
    fakePush();
    brand('Bosch');
    brand('Valeo');

    $result = resolve(OutboxPusher::class)->push();

    expect($result)->toBe(['pushed' => 2, 'rejected' => 0, 'remaining' => 0])
        ->and(OutboxEvent::pending()->count())->toBe(0)
        ->and(OutboxEvent::whereNotNull('pushed_at')->count())->toBe(2);

    Http::assertSent(function (Request $request) {
        [$first, $second] = $request['events'];

        return $request->hasHeader('Authorization', 'Bearer secret')
            && $first['payload']['name'] === 'Bosch' && $second['payload']['name'] === 'Valeo'
            && $first['event_type'] === 'brand.created'
            && $first['aggregate_type'] === 'brand'
            && preg_match('/^[0-9a-f-]{36}$/', $first['event_id'])
            && preg_match('/^[0-9a-f-]{36}$/', $first['aggregate_id'])
            && $first['base_version'] === null
            && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $first['occurred_at']);
    });
});

test('large backlogs go out in batches of the configured size', function () {
    config(['control.sync.batch_size' => 2]);
    fakePush();
    foreach (['A', 'B', 'C', 'D', 'E'] as $name) {
        brand($name);
    }

    $result = resolve(OutboxPusher::class)->push();

    expect($result['pushed'])->toBe(5);
    Http::assertSentCount(3);
});

test('a run stops after the maximum number of batches and leaves the rest for the next run', function () {
    config(['control.sync.batch_size' => 2, 'control.sync.max_batches_per_run' => 2]);
    fakePush();
    foreach (['A', 'B', 'C', 'D', 'E'] as $name) {
        brand($name);
    }

    expect(resolve(OutboxPusher::class)->push())->toBe(['pushed' => 4, 'rejected' => 0, 'remaining' => 1]);
});

test('an event Jocko Control already has counts as delivered, so retries are harmless', function () {
    brand('Bosch');
    fakePush([OutboxEvent::sole()->event_id => 'already_accepted']);

    expect(resolve(OutboxPusher::class)->push()['pushed'])->toBe(1)
        ->and(OutboxEvent::pending()->count())->toBe(0);
});

test('an event refused for good is parked with its reason and does not block the others', function () {
    brand('Bosch');
    brand('Valeo');
    $bad = OutboxEvent::orderBy('id')->first();
    fakePush([$bad->event_id => ['code' => 'payload_too_large', 'message' => 'Too big.']]);

    $result = resolve(OutboxPusher::class)->push();

    expect($result)->toBe(['pushed' => 1, 'rejected' => 1, 'remaining' => 0])
        ->and($bad->fresh()->rejected_code)->toBe('payload_too_large')
        ->and($bad->fresh()->last_error)->toBe('Too big.')
        ->and($bad->fresh()->pushed_at)->toBeNull();

    resolve(OutboxPusher::class)->push();
    Http::assertSentCount(1);
});

test('while Jocko Control is unreachable every event waits and is tried again later', function () {
    brand('Bosch');
    fakeHttp(['*' => fn () => throw new ConnectionException('timeout')]);

    expect(fn () => resolve(OutboxPusher::class)->push())->toThrow(ControlException::class);

    $event = OutboxEvent::sole();
    expect($event->pushed_at)->toBeNull()->and($event->attempts)->toBe(1)->and($event->last_error)->toContain('unreachable');

    fakePush();
    expect(resolve(OutboxPusher::class)->push()['pushed'])->toBe(1);
});

test('control:push treats an unreachable server as normal and a revoked token as a failure', function () {
    brand('Bosch');
    fakeHttp(['*' => fn () => throw new ConnectionException('offline')]);
    $this->artisan('control:push')->assertSuccessful();

    fakeHttp(['*' => Http::response(['error' => ['code' => 'device_revoked', 'message' => 'Revoked.']], 401)]);
    $this->artisan('control:push')->assertFailed();

    expect(OutboxEvent::pending()->count())->toBe(1);
});

test('control:push does nothing when the shop is not linked', function () {
    config(['control.url' => null]);

    $this->artisan('control:push')->expectsOutputToContain('not configured')->assertSuccessful();

    Http::assertNothingSent();
});

test('an answer that confirms nothing is an error instead of an endless loop', function () {
    brand('Bosch');
    fakeHttp(['*/sync/events' => Http::response(['data' => ['accepted' => [], 'already_accepted' => [], 'rejected' => []]])]);

    expect(fn () => resolve(OutboxPusher::class)->push())->toThrow(ControlException::class);
    Http::assertSentCount(1);
});

test('the first push also queues everything that existed before, once, deleted records included', function () {
    Brand::unguarded(fn () => DB::table('brands')->insert([
        ['slug' => 'old', 'name' => 'Old', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null],
        ['slug' => 'gone', 'name' => 'Gone', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => now()],
    ]));
    DB::table('control_state')->delete();
    fakePush();

    resolve(OutboxPusher::class)->push();
    resolve(OutboxPusher::class)->push();

    $snapshots = OutboxEvent::where('event_type', 'brand.snapshot')->get();

    expect($snapshots)->toHaveCount(2)
        ->and($snapshots->pluck('payload.name')->sort()->values()->all())->toBe(['Gone', 'Old'])
        ->and($snapshots->firstWhere('payload.name', 'Gone')->payload['deleted_at'])->not->toBeNull();
    Http::assertSentCount(1);
});

test('control:resync queues a fresh copy of the chosen entities on demand', function () {
    fakePush();
    brand('Bosch');
    resolve(OutboxPusher::class)->push();

    $this->artisan('control:resync', ['entity' => ['brand']])->assertSuccessful();

    expect(OutboxEvent::pending()->pluck('event_type')->all())->toBe(['brand.snapshot']);
});

test('pushed events are kept for the retention period and then pruned, pending ones never', function () {
    fakePush();
    brand('Old');
    resolve(OutboxPusher::class)->push();
    OutboxEvent::query()->update(['pushed_at' => now()->subDays(30)]);
    brand('Fresh');
    fakeHttp(['*' => fn () => throw new ConnectionException('offline')]);
    try {
        resolve(OutboxPusher::class)->push();
    } catch (ControlException) {
    }
    fakePush();
    resolve(OutboxPusher::class)->push();
    OutboxEvent::query()->whereNotNull('pushed_at')->where('id', '>', 1)->update(['pushed_at' => now()]);
    resolve(OutboxPusher::class)->push();

    expect(OutboxEvent::count())->toBe(1)->and(OutboxEvent::sole()->payload['name'])->toBe('Fresh');
});

test('the next heartbeat tells Jocko Control how the push went, once, and how much is waiting', function () {
    fakePush();
    brand('Bosch');
    resolve(OutboxPusher::class)->push();
    brand('Valeo');

    $first = resolve(DeviceStatus::class)->heartbeatPayload();
    $second = resolve(DeviceStatus::class)->heartbeatPayload();

    expect($first['sync'])->toBe(['status' => 'success'])
        ->and($first['local_status']['pending_events'])->toBe(1)
        ->and($second)->not->toHaveKey('sync');
});

test('a failed push is reported as such with the reason', function () {
    brand('Bosch');
    fakeHttp(['*' => Http::response(['error' => ['code' => 'shop_suspended', 'message' => 'This shop is suspended.']], 403)]);

    try {
        resolve(OutboxPusher::class)->push();
    } catch (ControlException) {
    }

    $report = resolve(DeviceStatus::class)->heartbeatPayload()['sync'];

    expect($report['status'])->toBe('failed')->and($report['error'])->toContain('suspended');
});

test('the snapshot of a large table is read in chunks without loading it all', function () {
    DB::table('brands')->insert(array_map(fn (int $i) => ['slug' => "b{$i}", 'name' => "B{$i}", 'is_active' => true, 'created_at' => now(), 'updated_at' => now()], range(1, 1200)));

    $queued = resolve(OutboxSnapshot::class)->enqueue(['brand']);

    expect($queued)->toBe(['brand' => 1200])->and(OutboxEvent::count())->toBe(1200);
});

/**
 * The scheduler entry that pushes waiting changes every few seconds.
 */
function fastPushEvent(): Illuminate\Console\Scheduling\CallbackEvent
{
    return collect(resolve(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($event) => $event->description === 'control:push-fast');
}

test('changes are pushed every five seconds, not only at the next minute', function () {
    $event = fastPushEvent();

    expect($event)->not->toBeNull()->and($event->repeatSeconds)->toBe(5);
});

test('the five-second push does nothing while nothing is waiting or the shop is not linked', function () {
    expect(fastPushEvent()->filtersPass(app()))->toBeFalse();

    brand('Bosch');
    expect(fastPushEvent()->filtersPass(app()))->toBeTrue();

    config(['control.url' => null]);
    expect(fastPushEvent()->filtersPass(app()))->toBeFalse();
});

test('the five-second push sends what is waiting', function () {
    fakePush();
    brand('Bosch');

    fastPushEvent()->run(app());

    expect(OutboxEvent::pending()->count())->toBe(0);
    Http::assertSentCount(1);
});

test('one five-second tick sends at most a few batches so the rate limit is never at risk', function () {
    config(['control.sync.batch_size' => 1]);
    fakePush();
    foreach (range(1, 8) as $i) {
        brand("Brand {$i}");
    }

    fastPushEvent()->run(app());

    Http::assertSentCount(5);
    expect(OutboxEvent::pending()->count())->toBe(3);
});

test('an unreachable server does not break the five-second tick and the changes wait', function () {
    brand('Bosch');
    fakeHttp(['*' => fn () => throw new ConnectionException('offline')]);

    fastPushEvent()->run(app());

    expect(OutboxEvent::pending()->count())->toBe(1)->and(OutboxEvent::sole()->attempts)->toBe(1);
});
