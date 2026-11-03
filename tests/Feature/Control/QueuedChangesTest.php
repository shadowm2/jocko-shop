<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Control\Models\OutboxEvent;
use Modules\Control\Services\ControlLink;
use Modules\Dashboard\Livewire\JockoControlQueue;
use Modules\User\Models\User;

beforeEach(function () {
    Cache::flush();
    // Not linked yet, so signing in a user does not itself record a change.
    config(['control.url' => null, 'control.token' => null]);
    $this->actingAs(User::factory()->create());
    config(['control.url' => 'https://control.test', 'control.token' => 'secret']);
    resolve(ControlLink::class)->markSnapshotQueued();

    $this->queue = function (string $type, int $id, array $extra = []): OutboxEvent {
        [$entity] = explode('.', $type);

        return OutboxEvent::create([
            'event_id' => (string) Str::uuid(), 'event_type' => $type, 'aggregate_type' => $entity,
            'aggregate_id' => (string) Str::uuid(), 'payload' => ['id' => $id], 'occurred_at' => now(),
        ] + $extra);
    };

    $this->waiting = ($this->queue)('order.created', 7);
    $this->refused = ($this->queue)('payment.updated', 3, ['rejected_code' => 'validation_failed', 'last_error' => 'The payload is too large.', 'attempts' => 2]);
    $this->sent = ($this->queue)('brand.created', 1, ['pushed_at' => now()]);
});

it('is reachable from the menu and renders for a signed in user', function () {
    $this->get(route('jocko-control-queue.index'))->assertOk()->assertSee(route('jocko-control-queue.index'), false);
});

it('needs a signed in user', function () {
    auth()->logout();

    $this->get(route('jocko-control-queue.index'))->assertRedirect(route('login'));
});

it('lists every recorded change with how it stands, newest first', function () {
    Livewire::test(JockoControlQueue::class)
        ->assertSeeInOrder(['brand', 'payment', 'order'])
        ->assertSee('#7')
        ->assertSee('validation_failed')
        ->assertSee('The payload is too large.');
});

it('counts waiting, refused and sent changes', function () {
    ($this->queue)('order.updated', 8);

    Livewire::test(JockoControlQueue::class)
        ->assertViewHas('counts', [JockoControlQueue::WAITING => 2, JockoControlQueue::REFUSED => 1, JockoControlQueue::SENT => 1]);
});

it('filters by status and by type', function () {
    $ids = fn ($component) => $component->viewData('rows')->pluck('id')->all();

    expect($ids(Livewire::test(JockoControlQueue::class)->set('filters.status', JockoControlQueue::WAITING)))->toBe([$this->waiting->id])
        ->and($ids(Livewire::test(JockoControlQueue::class)->set('filters.status', JockoControlQueue::REFUSED)))->toBe([$this->refused->id])
        ->and($ids(Livewire::test(JockoControlQueue::class)->set('filters.status', JockoControlQueue::SENT)))->toBe([$this->sent->id])
        ->and($ids(Livewire::test(JockoControlQueue::class)->set('filters.search', 'payment')))->toBe([$this->refused->id])
        ->and($ids(Livewire::test(JockoControlQueue::class)->set('filters.search', '%')))->toBe([]);
});

it('queues the refused changes again', function () {
    Livewire::test(JockoControlQueue::class)->call('retryRefused')->assertHasNoErrors();

    $retried = $this->refused->fresh();

    expect($retried->rejected_code)->toBeNull()
        ->and($retried->last_error)->toBeNull()
        ->and($retried->attempts)->toBe(0)
        ->and(OutboxEvent::pending()->count())->toBe(2)
        ->and($this->sent->fresh()->pushed_at)->not->toBeNull();
});

it('sends what is waiting right away', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*/sync/events' => fn (Request $request) => Http::response(['data' => [
        'accepted' => collect($request['events'])->map(fn (array $event, int $index) => ['index' => $index, 'event_id' => $event['event_id']])->all(),
        'already_accepted' => [], 'rejected' => [],
    ]])]);

    Livewire::test(JockoControlQueue::class)->call('pushNow');

    expect(OutboxEvent::pending()->count())->toBe(0)
        ->and($this->waiting->fresh()->pushed_at)->not->toBeNull();
});

it('says so instead of failing when Control cannot be reached', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*/sync/events' => Http::response(['error' => ['code' => 'server_error', 'message' => 'boom']], 500)]);

    Livewire::test(JockoControlQueue::class)->call('pushNow')->assertOk();

    expect(OutboxEvent::pending()->count())->toBe(1)
        ->and($this->waiting->fresh()->attempts)->toBe(1);
});

it('does nothing and explains when the shop is not linked to Control', function () {
    config(['control.url' => null, 'control.token' => null]);
    Http::preventStrayRequests();

    Livewire::test(JockoControlQueue::class)->assertSee(__('dashboard::strings.Queued Changes Not Linked'))->call('pushNow')->assertOk();

    Http::assertNothingSent();
});
