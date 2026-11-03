@php
    use App\Helpers\Utils;
    use Modules\Order\Enums\PaymentAttemptStatus;

    $money = fn ($value) => Utils::pDigits(Utils::formatQuantity($value, 2, ','));
    $open = $attempt?->isOpen() ? $attempt : null;
@endphp
<div class="mx-auto flex w-full max-w-3xl flex-col gap-5">
    <div class="flex items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('order::strings.Collect Payment') }}</flux:heading>
            <flux:subheading>
                <span dir="ltr">{{ $order->order_number }}</span>
                @if ($order->user) · {{ $order->user->fullName() }} @endif
            </flux:subheading>
        </div>
        <flux:button :href="route('orders.index')" variant="ghost" icon="arrow-uturn-left">{{ __('order::strings.Back To Orders') }}</flux:button>
    </div>

    <dl class="grid grid-cols-3 gap-3" data-order-balance>
        <flux:card>
            <dt class="text-xs text-zinc-500">{{ __('order::attributes.Total') }}</dt>
            <dd class="text-lg font-semibold">{{ $money($total) }}</dd>
        </flux:card>
        <flux:card>
            <dt class="text-xs text-zinc-500">{{ __('order::strings.Paid Total') }}</dt>
            <dd class="text-lg font-semibold text-green-600">{{ $money($paid) }}</dd>
        </flux:card>
        <flux:card>
            <dt class="text-xs text-zinc-500">{{ __('order::attributes.Balance') }}</dt>
            <dd class="text-lg font-semibold" data-balance="{{ $balance }}">{{ $money($balance) }}</dd>
        </flux:card>
    </dl>

    @if ($open)
        <flux:card
            class="space-y-4"
            data-open-attempt="{{ $open->kind }}"
            wire:poll.4s="refreshAttempt"
        >
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">
                    {{ $open->isOnline() ? __('order::strings.Waiting For The Customer') : __('order::strings.Waiting For The Terminal') }}
                </flux:heading>
                <flux:badge :color="$open->status->color()">{{ $open->status->label() }}</flux:badge>
            </div>

            <div class="text-2xl font-semibold">{{ $money($open->amount) }}</div>

            @if ($open->isOnline())
                <flux:text>{{ __('order::strings.Online Instructions') }}</flux:text>
                @if ($qr)
                    <div class="flex flex-wrap items-center gap-4">
                        <div
                            class="shrink-0 rounded-lg border border-zinc-200 bg-white p-2 [&_svg]:size-44"
                            data-payment-qr
                            role="img"
                            aria-label="{{ __('order::strings.Scan To Pay') }}"
                        >{!! $qr !!}</div>
                        <flux:text size="sm" class="text-zinc-500">{{ __('order::strings.Scan To Pay') }}</flux:text>
                    </div>
                @endif
                <div class="flex flex-wrap items-center gap-2">
                    <flux:button :href="$open->pay_url" target="_blank" variant="primary" icon="arrow-top-right-on-square">{{ __('order::strings.Open Payment Page') }}</flux:button>
                    <flux:button wire:click="sendLink" wire:loading.attr="disabled" wire:target="sendLink" icon="chat-bubble-left-ellipsis" data-send-link>{{ __('order::strings.Send Link By Sms') }}</flux:button>
                    <div class="min-w-0 flex-1">
                        <flux:input readonly copyable :value="$open->pay_url" dir="ltr" />
                    </div>
                </div>
            @else
                <flux:text>{{ __('order::strings.Pos Instructions', ['amount' => $money($open->amount)]) }}</flux:text>
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('order::strings.Terminal Reference') }}</flux:label>
                        <flux:input wire:model="terminalReference" dir="ltr" maxlength="50" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('order::strings.Card Last Digits') }}</flux:label>
                        <flux:input wire:model="cardLastDigits" dir="ltr" maxlength="4" inputmode="numeric" />
                    </flux:field>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="confirmCard" variant="primary" icon="check">{{ __('order::strings.Terminal Approved') }}</flux:button>
                    <flux:button wire:click="declineCard" wire:confirm="{{ __('order::strings.Terminal Declined Confirm') }}" icon="x-mark">{{ __('order::strings.Terminal Declined') }}</flux:button>
                </div>
            @endif

            <flux:button wire:click="cancel" wire:confirm="{{ __('order::strings.Cancel Payment Confirm') }}" variant="ghost" size="sm">{{ __('order::strings.Cancel Payment') }}</flux:button>
        </flux:card>
    @elseif ($balance > 0)
        <flux:card class="space-y-5">
            <flux:field>
                <flux:label>{{ __('order::attributes.Amount') }}</flux:label>
                <flux:input wire:model="amount" type="number" step="any" min="0" dir="ltr" data-amount />
                <flux:description>{{ __('order::strings.Amount Hint') }}</flux:description>
                <flux:error name="amount" />
            </flux:field>

            <div class="space-y-2">
                <flux:heading>{{ __('order::strings.Pay Online') }}</flux:heading>
                @if ($gateways === [])
                    <flux:callout icon="information-circle">
                        <flux:callout.text>
                            {{ __('order::strings.No Gateway Configured') }}
                            <flux:link :href="route('system-settings.edit')">{{ __('dashboard::strings.System Settings') }}</flux:link>
                        </flux:callout.text>
                    </flux:callout>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($gateways as $key => $gateway)
                            <flux:button wire:click="payOnline('{{ $key }}')" wire:loading.attr="disabled" icon="credit-card" data-gateway="{{ $key }}">{{ $gateway->label() }}</flux:button>
                        @endforeach
                    </div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('order::strings.Callback Hint', ['url' => $callbackBase]) }}</flux:text>
                @endif
            </div>

            <div class="space-y-2">
                <flux:heading>{{ __('order::strings.Pay By Card Terminal') }}</flux:heading>
                <div class="flex flex-wrap gap-2">
                    @foreach ($terminals as $key => $terminal)
                        <flux:button wire:click="payByCard('{{ $key }}')" wire:loading.attr="disabled" icon="device-phone-mobile" data-terminal="{{ $key }}">{{ $terminal->label() }}</flux:button>
                    @endforeach
                </div>
            </div>
        </flux:card>
    @else
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ __('order::strings.Order Fully Paid') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($history->isNotEmpty())
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('order::strings.Payment Attempts') }}</flux:heading>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800" data-attempt-history>
                @foreach ($history as $past)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm" wire:key="attempt-{{ $past->id }}">
                        <div class="flex items-center gap-2">
                            <flux:badge :color="$past->status->color()" size="sm">{{ $past->status->label() }}</flux:badge>
                            <span>{{ $past->kind === 'pos' ? __('order::strings.Card terminal') : ucfirst($past->driver) }}</span>
                            @if ($past->result_reference)
                                <span class="text-zinc-500" dir="ltr">#{{ $past->result_reference }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            @if ($past->failure && $past->status !== PaymentAttemptStatus::PAID)
                                <span class="max-w-xs truncate text-xs text-zinc-500" title="{{ $past->failure }}">{{ $past->failure }}</span>
                            @endif
                            <span class="font-medium">{{ $money($past->amount) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
