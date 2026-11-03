@php
    use App\Helpers\Utils;
    use Morilog\Jalali\Jalalian;

    $money = fn ($value) => Utils::pDigits(Utils::formatQuantity($value, 0, ','));
    $date = fn ($value) => Utils::pDigits(Jalalian::fromDateTime($value)->format('Y/m/d'));
@endphp
<div class="mx-auto max-w-3xl space-y-6 p-8 text-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
            <p class="text-zinc-500">{{ __('order::strings.Statement') }}</p>
            @if (filled(config('shop.phone')))
                <p class="text-zinc-500" dir="ltr">{{ Utils::pDigits(config('shop.phone')) }}</p>
            @endif
        </div>
        <div class="space-y-1 text-end">
            <div class="font-semibold">{{ $user->fullName() }}</div>
            <div class="text-zinc-500" dir="ltr">{{ Utils::pDigits((string) $user->mobile) }}</div>
            <div class="text-zinc-500">{{ $date(now()) }}</div>
            <button type="button" class="rounded border border-zinc-300 px-2 py-0.5 text-xs print:hidden" onclick="window.print()">{{ __('order::strings.Print') }}</button>
        </div>
    </div>

    <table class="w-full border-collapse" data-statement-orders>
        <thead>
            <tr class="border-b border-zinc-300 text-zinc-500">
                <th class="py-2 text-start font-medium">{{ __('order::attributes.Order Number') }}</th>
                <th class="py-2 text-start font-medium">{{ __('order::strings.Date') }}</th>
                <th class="py-2 text-end font-medium">{{ __('order::attributes.Total') }}</th>
                <th class="py-2 text-end font-medium">{{ __('order::strings.Paid Total') }}</th>
                <th class="py-2 text-end font-medium">{{ __('order::attributes.Balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                @php $owed = round((float) $order->items_sum_total - (float) $order->payments_sum_amount, 2); @endphp
                <tr class="border-b border-zinc-200" data-statement-order="{{ $order->order_number }}">
                    <td class="py-2" dir="ltr">{{ $order->order_number }}</td>
                    <td class="py-2">{{ $date($order->created_at) }}</td>
                    <td class="py-2 text-end tabular-nums">{{ $money($order->items_sum_total) }}</td>
                    <td class="py-2 text-end tabular-nums">{{ $money($order->payments_sum_amount ?? 0) }}</td>
                    <td @class(['py-2 text-end font-medium tabular-nums', 'text-red-600' => $owed > 0])>{{ $money($owed) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-zinc-500">{{ __('order::strings.No Orders Yet') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="ms-auto w-full max-w-xs space-y-1" data-statement-summary>
        <div class="flex justify-between"><span>{{ __('order::attributes.Total') }}</span><span class="tabular-nums">{{ $money($summary['total']) }}</span></div>
        <div class="flex justify-between"><span>{{ __('order::strings.Paid Total') }}</span><span class="tabular-nums">{{ $money($summary['paid']) }}</span></div>
        <div class="flex justify-between border-t border-zinc-300 pt-1 text-base font-bold"><span>{{ __('order::strings.Balance Owed') }}</span><span class="tabular-nums" data-balance="{{ $summary['balance'] }}">{{ $money($summary['balance']) }}</span></div>
    </div>

    @if ($payments->isNotEmpty())
        <div class="space-y-2">
            <h2 class="font-semibold">{{ __('order::strings.Payments') }}</h2>
            @foreach ($payments as $payment)
                <div class="flex justify-between border-b border-zinc-200 py-1">
                    <span>{{ $date($payment->paid_at) }} · {{ $payment->method->label() }}</span>
                    <span class="tabular-nums">{{ $money($payment->amount) }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
