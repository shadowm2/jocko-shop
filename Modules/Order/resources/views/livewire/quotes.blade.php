@php
    use App\Helpers\Utils;
    use Modules\Order\Enums\QuoteStatus;
    use Morilog\Jalali\Jalalian;
@endphp
<div class="flex w-full max-w-5xl flex-col gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('order::strings.Quotes') }}</flux:heading>
            <flux:subheading>{{ __('order::strings.Quotes Description') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('orders.create')" wire:navigate>{{ __('order::strings.New Quote') }}</flux:button>
    </div>

    <div class="flex flex-wrap gap-3">
        <div class="min-w-48 flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('order::strings.Search Quotes')" />
        </div>
        <div class="w-48">
            <flux:select wire:model.live="status">
                <flux:select.option value="">{{ __('strings.All') }}</flux:select.option>
                @foreach (QuoteStatus::cases() as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @forelse ($quotes as $quote)
        <flux:card class="space-y-2" wire:key="quote-{{ $quote->id }}" data-quote="{{ $quote->quote_number }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold" dir="ltr">{{ $quote->quote_number }}</span>
                    <flux:badge size="sm" :color="$quote->status->color()">{{ $quote->status->label() }}</flux:badge>
                    @if ($quote->isExpired())
                        <flux:badge size="sm" color="amber" data-quote-expired>{{ __('order::strings.Quote Expired') }}</flux:badge>
                    @endif
                </div>
                <span class="font-semibold">{{ Utils::pDigits(number_format((float) $quote->items_sum_total)) }}</span>
            </div>
            <div class="text-sm text-zinc-500">
                {{ $quote->user->fullName() }} · {{ $quote->userCar->car->name }}
                @if ($quote->valid_until)
                    · {{ __('order::strings.Quote Valid Until', ['date' => Utils::pDigits(Jalalian::fromDateTime($quote->valid_until)->format('Y/m/d'))]) }}
                @endif
            </div>
            <div class="flex flex-wrap gap-2 pt-1">
                <flux:button size="xs" icon="printer" :href="route('quotes.print', $quote)" target="_blank">{{ __('order::strings.Print') }}</flux:button>
                @if ($quote->status !== QuoteStatus::CONVERTED && $quote->status !== QuoteStatus::REJECTED)
                    <flux:button size="xs" icon="shopping-bag" variant="primary" wire:click="openInOrderForm({{ $quote->id }})" data-open-quote>
                        {{ $quote->status->isOpen() ? __('order::strings.Quote Edit Or Order') : __('order::strings.Quote Create Order') }}
                    </flux:button>
                    <flux:button size="xs" icon="chat-bubble-left-right" wire:click="sendSms({{ $quote->id }})">{{ __('order::strings.Quote Send Sms') }}</flux:button>
                @endif
                @if ($quote->status->isOpen())
                    <flux:button size="xs" wire:click="setStatus({{ $quote->id }}, 'accepted')" data-accept-quote>{{ __('order::strings.Quote Accept') }}</flux:button>
                    <flux:button size="xs" wire:click="setStatus({{ $quote->id }}, 'rejected')" data-reject-quote>{{ __('order::strings.Quote Reject') }}</flux:button>
                @endif
                @if ($quote->status === QuoteStatus::CONVERTED && $quote->order_id)
                    <flux:button size="xs" icon="arrow-top-right-on-square" :href="route('orders.edit', $quote->order_id)" wire:navigate>{{ __('order::strings.Quote Open Order') }}</flux:button>
                @endif
                @if ($quote->status !== QuoteStatus::CONVERTED)
                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $quote->id }})" wire:confirm="{{ __('order::strings.Quote Delete Confirm') }}" data-delete-quote />
                @endif
            </div>
        </flux:card>
    @empty
        <flux:card>
            <flux:text class="py-8 text-center text-zinc-500" data-no-quotes>{{ __('order::strings.No Quotes') }}</flux:text>
        </flux:card>
    @endforelse

    {{ $quotes->links() }}
</div>
