@php
    use App\Helpers\Utils;
    use Morilog\Jalali\Jalalian;

    $money = fn ($value): string => Utils::pDigits(Utils::formatQuantity((float) $value, 0, ','));
    $date = fn ($value): string => Utils::pDigits(Jalalian::fromDateTime($value)->format('Y/m/d'));
    $remaining = round($total - $paid, 2);
@endphp
<div class="mx-auto text-black" style="width: {{ $width }}mm" data-receipt="{{ $width }}">
    <style>
        @page { size: {{ $width }}mm auto; margin: 2mm; }
        @media print { body { background: #fff; } .no-print { display: none !important; } }
    </style>

    <div class="space-y-1 px-1 py-2 text-[11px] leading-5" style="font-size: {{ $width === 58 ? 10 : 12 }}px">
        <div class="text-center">
            <div class="text-base font-bold">{{ config('app.name') }}</div>
            @if (filled(config('shop.address')))
                <div>{{ config('shop.address') }}</div>
            @endif
            @if (filled(config('shop.phone')))
                <div dir="ltr">{{ Utils::pDigits(config('shop.phone')) }}</div>
            @endif
            <div class="mt-1 font-semibold">{{ __('order::strings.Receipt') }}</div>
        </div>

        <hr class="border-dashed border-black">

        <div class="flex justify-between"><span>{{ __('order::strings.Date') }}</span><span>{{ $date($payment->paid_at) }}</span></div>
        <div class="flex justify-between"><span>{{ __('order::attributes.Order Number') }}</span><span dir="ltr">{{ $order->order_number }}</span></div>
        <div class="flex justify-between"><span>{{ __('order::strings.Customer') }}</span><span>{{ $order->user->fullName() }}</span></div>
        @if ($order->userCar?->car)
            <div class="flex justify-between"><span>{{ __('order::attributes.User Car') }}</span><span>{{ $order->userCar->car->name }}</span></div>
        @endif

        <hr class="border-dashed border-black">

        <div class="flex justify-between"><span>{{ __('order::attributes.Payment Method') }}</span><span>{{ $payment->method->label() }}</span></div>
        @if (filled($payment->cheque_number))
            <div class="flex justify-between"><span>{{ __('order::attributes.Cheque Number') }}</span><span dir="ltr">{{ Utils::pDigits($payment->cheque_number) }}</span></div>
            @if ($payment->cheque_due_at)
                <div class="flex justify-between"><span>{{ __('order::attributes.Cheque Due At') }}</span><span>{{ $date($payment->cheque_due_at) }}</span></div>
            @endif
        @endif
        <div class="flex justify-between text-base font-bold" data-receipt-amount="{{ $payment->amount }}"><span>{{ __('order::attributes.Amount') }}</span><span>{{ $money($payment->amount) }}</span></div>

        <hr class="border-dashed border-black">

        <div class="flex justify-between"><span>{{ __('order::attributes.Total') }}</span><span>{{ $money($total) }}</span></div>
        <div class="flex justify-between"><span>{{ __('order::strings.Paid Total') }}</span><span>{{ $money($paid) }}</span></div>
        <div class="flex justify-between font-semibold" data-receipt-remaining="{{ $remaining }}"><span>{{ __('order::attributes.Balance') }}</span><span>{{ $money($remaining) }}</span></div>

        @if (filled($payment->description))
            <div class="pt-1 text-center">{{ $payment->description }}</div>
        @endif

        <div class="pt-2 text-center">{{ __('order::strings.Thank You') }}</div>

        <div class="no-print flex justify-center gap-2 pt-3">
            <button type="button" class="rounded border border-zinc-400 px-2 py-0.5 text-xs" onclick="window.print()" data-print>{{ __('order::strings.Print') }}</button>
            @foreach (\Modules\Order\Livewire\PaymentReceipt::WIDTHS as $paper)
                <a href="{{ route('payments.receipt', ['payment' => $payment, 'width' => $paper]) }}" @class(['rounded border px-2 py-0.5 text-xs', 'border-black font-semibold' => $paper === $width, 'border-zinc-300' => $paper !== $width])>{{ Utils::pDigits((string) $paper) }} {{ __('order::strings.Mm') }}</a>
            @endforeach
        </div>
    </div>
</div>
