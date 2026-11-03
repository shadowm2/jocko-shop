<?php

namespace Modules\Dashboard\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Models\OutboxEvent;
use Modules\Control\Services\ControlClient;
use Modules\Control\Services\ControlLink;
use Modules\Control\Services\OutboxPusher;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableFilters;

/**
 * The changes this shop has recorded for Jocko Control: what is still waiting,
 * what Control refused for good, and what was sent recently.
 */
class JockoControlQueue extends Component
{
    use InteractsWithTableFilters;
    use WithPagination;

    public const WAITING = 'waiting';

    public const REFUSED = 'refused';

    public const SENT = 'sent';

    /** @var array<string, mixed> */
    public array $filters = ['search' => '', 'status' => ''];

    /** @return array<string, mixed> */
    protected function defaultTableFilters(): array
    {
        return ['search' => '', 'status' => ''];
    }

    /**
     * Send what is waiting right now instead of at the next scheduled run.
     */
    public function pushNow(OutboxPusher $pusher, ControlClient $client): void
    {
        if (! $client->isConfigured()) {
            Flux::toast(__('dashboard::messages.Jocko Control Not Linked'), variant: 'warning');

            return;
        }

        try {
            $result = $pusher->push();
        } catch (ControlException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        Flux::toast(__('dashboard::messages.Queued Changes Sent', ['count' => $result['pushed']]), variant: $result['rejected'] > 0 ? 'warning' : 'success');
    }

    /**
     * Give the changes Control refused another chance, e.g. after it was fixed.
     */
    public function retryRefused(): void
    {
        OutboxEvent::query()
            ->whereNotNull('rejected_code')
            ->update(['rejected_code' => null, 'last_error' => null, 'attempts' => 0]);

        $this->resetPage();
        Flux::toast(__('dashboard::messages.Refused Changes Queued Again'), variant: 'success');
    }

    /**
     * @param  Builder<OutboxEvent>  $query
     */
    private function withStatus(Builder $query, string $status): void
    {
        match ($status) {
            self::WAITING => $query->pending(),
            self::REFUSED => $query->whereNotNull('rejected_code'),
            self::SENT => $query->whereNotNull('pushed_at'),
            default => null,
        };
    }

    public function render(ControlClient $client, ControlLink $link): View
    {
        $search = trim((string) $this->filters['search']);

        $rows = OutboxEvent::query()
            ->when($search !== '', fn (Builder $query) => $query->where('event_type', 'like', '%'.addcslashes($search, '\\%_').'%'))
            ->tap(fn (Builder $query) => $this->withStatus($query, (string) $this->filters['status']))
            ->orderByDesc('id')
            ->paginate(25);

        $columns = [
            ['label' => __('dashboard::strings.Recorded At')],
            ['label' => __('dashboard::strings.Change')],
            ['label' => __('dashboard::strings.Record')],
            ['label' => __('dashboard::strings.Status')],
            ['label' => __('dashboard::strings.Attempts')],
            ['label' => __('dashboard::strings.Last Error')],
        ];

        $filterDefinitions = [
            ['name' => 'search', 'type' => 'search', 'label' => __('dashboard::strings.Search'), 'placeholder' => __('dashboard::strings.Search Queued Changes')],
            ['name' => 'status', 'type' => 'select', 'label' => __('dashboard::strings.Status'), 'placeholder' => __('strings.All'), 'options' => [
                self::WAITING => __('dashboard::strings.Waiting'),
                self::REFUSED => __('dashboard::strings.Refused'),
                self::SENT => __('dashboard::strings.Sent'),
            ]],
        ];

        $counts = [
            self::WAITING => OutboxEvent::query()->pending()->count(),
            self::REFUSED => OutboxEvent::query()->whereNotNull('rejected_code')->count(),
            self::SENT => OutboxEvent::query()->whereNotNull('pushed_at')->count(),
        ];

        return view('dashboard::livewire.jocko-control-queue', [
            ...compact('columns', 'rows', 'filterDefinitions', 'counts'),
            'linked' => $client->isConfigured(),
            'lastError' => $link->lastError(),
        ])->layout('dashboard::layouts.app')->layoutData(['title' => __('dashboard::strings.Queued Changes')]);
    }
}
