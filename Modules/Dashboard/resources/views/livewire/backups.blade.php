@php
    use App\Helpers\Utils;
    use Morilog\Jalali\Jalalian;
@endphp
<div class="flex w-full max-w-3xl flex-col gap-5">
    <div class="flex items-start justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('dashboard::strings.Backups') }}</flux:heading>
            <flux:subheading>{{ __('dashboard::strings.Backups Description', ['keep' => Utils::pDigits((string) $keep)]) }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="arrow-down-tray" wire:click="create" wire:loading.attr="disabled" wire:target="create" data-backup-now>{{ __('dashboard::strings.Back Up Now') }}</flux:button>
    </div>

    @if ($lastError)
        <flux:callout variant="danger" icon="exclamation-triangle" data-backup-failed>
            <flux:callout.text>{{ __('dashboard::strings.Last Backup Failed', ['message' => $lastError['message']]) }}</flux:callout.text>
        </flux:callout>
    @elseif ($lastOk === null)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.text>{{ __('dashboard::strings.No Backup Yet') }}</flux:callout.text>
        </flux:callout>
    @endif

    <flux:card class="space-y-3">
        @forelse ($files as $file)
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 pb-2 last:border-0 dark:border-zinc-800" data-backup-file="{{ $file['name'] }}">
                <div>
                    <div class="text-sm font-medium" dir="ltr">{{ $file['name'] }}</div>
                    <div class="text-xs text-zinc-500">{{ Utils::pDigits(Jalalian::fromCarbon(\Illuminate\Support\Carbon::createFromTimestamp($file['at']))->format('Y/m/d H:i')) }} · {{ Utils::pDigits(number_format($file['size'] / 1024)) }} KB</div>
                </div>
                <flux:button size="sm" icon="arrow-down-tray" :href="route('backups.download', $file['name'])">{{ __('dashboard::strings.Download') }}</flux:button>
            </div>
        @empty
            <flux:text class="py-6 text-center text-zinc-500">{{ __('dashboard::strings.No Backup Yet') }}</flux:text>
        @endforelse
    </flux:card>

    <flux:text size="sm" class="text-zinc-500">
        {{ __('dashboard::strings.Backups Safety') }}
        @if ($copyTo) {{ __('dashboard::strings.Backups Copy', ['path' => $copyTo]) }} @endif
    </flux:text>
    <flux:text size="sm" class="text-zinc-500" dir="ltr">php artisan backup:restore &lt;file&gt;</flux:text>
</div>
