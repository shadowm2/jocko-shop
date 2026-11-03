@php
    use App\Helpers\Utils;
    use Morilog\Jalali\Jalalian;

    $money = fn ($value) => Utils::formatQuantity($value, 2, ',');
@endphp
<div class="mx-auto max-w-3xl space-y-6 p-8 text-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
            <p class="text-zinc-500">{{ __('order::strings.Invoice') }}</p>
            @if (filled(config('shop.address')))
                <p class="text-zinc-500">{{ config('shop.address') }}</p>
            @endif
            @if (filled(config('shop.phone')))
                <p class="text-zinc-500">{{ Utils::pDigits(config('shop.phone')) }}</p>
            @endif
        </div>
        <div class="space-y-1 text-end">
            <div class="font-semibold"><span dir="ltr">{{ $order->order_number }}</span></div>
            <div>{{ Utils::pDigits(Jalalian::fromDateTime($order->created_at)->format('Y/m/d')) }}</div>
            <div>{{ $order->status->label() }}</div>
            <flux:button
                class="print:hidden"
                icon="printer"
                size="sm"
                onclick="window.print()"
            >
                {{ __('order::strings.Print') }}
            </flux:button>
        </div>
    </div>

    <dl class="grid grid-cols-2 gap-x-6 gap-y-2 rounded-lg border border-zinc-200 p-4">
        <div>
            <dt class="text-zinc-500">{{ __('order::strings.Customer') }}</dt>
            <dd class="font-medium">{{ $order->user->fullName() }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('order::strings.Mobile') }}</dt>
            <dd class="font-medium">{{ Utils::pDigits($order->user->mobile) }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('order::attributes.User Car') }}</dt>
            <dd class="font-medium">{{ $order->userCar->car->name }}</dd>
        </div>
        @multiwarehouse
            <div>
                <dt class="text-zinc-500">{{ __('order::attributes.Warehouse') }}</dt>
                <dd class="font-medium">{{ $order->warehouse->name }}</dd>
            </div>
        @endmultiwarehouse
    </dl>

    <table class="w-full border-collapse">
        <thead>
            <tr class="border-b border-zinc-300 text-start text-zinc-500">
                <th class="py-2 text-start font-medium">{{ __('order::strings.Item') }}</th>
                <th class="py-2 text-start font-medium">{{ __('order::attributes.Quantity') }}</th>
                <th class="py-2 text-start font-medium">{{ __('order::attributes.Unit Price') }}</th>
                <th class="py-2 text-end font-medium">{{ __('order::attributes.Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $line)
                <tr class="border-b border-zinc-200">
                    <td class="py-2">{{ $line->item->name }}</td>
                    <td class="py-2">{{ Utils::formatQuantity($line->quantity) }} {{ $line->unit?->symbol }}</td>
                    <td class="py-2">{{ $money($line->unit_price) }}</td>
                    <td class="py-2 text-end">{{ $money($line->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="ms-auto w-full max-w-xs space-y-1">
        <div class="flex justify-between"><span>{{ __('order::attributes.Total') }}</span><span>{{ $money($total) }}</span></div>
        <div class="flex justify-between"><span>{{ __('order::strings.Paid Total') }}</span><span>{{ $money($paid) }}</span></div>
        <div class="flex justify-between border-t border-zinc-300 pt-1 text-base font-bold"><span>{{ __('order::attributes.Balance') }}</span><span>{{ $money($total - $paid) }}</span></div>
    </div>

    @if ($order->payments->isNotEmpty())
        <div class="space-y-2">
            <h2 class="font-semibold">{{ __('order::strings.Payments') }}</h2>
            @foreach ($order->payments->sortBy('paid_at') as $payment)
                <div class="flex justify-between border-b border-zinc-200 py-1">
                    <span>{{ Utils::pDigits($payment->paid_at_jalali?->format('Y/m/d')) }} · {{ $payment->method->label() }}</span>
                    <span>{{ $money($payment->amount) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if (filled($order->description))
        <p class="text-zinc-500">{{ $order->description }}</p>
    @endif
</div>
