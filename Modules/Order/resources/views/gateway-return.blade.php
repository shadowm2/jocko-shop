@php
    use App\Helpers\Utils;
    use Modules\Order\Enums\PaymentAttemptStatus;

    $status = $attempt->status;
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @if ($refresh)
        <meta http-equiv="refresh" content="5">
    @endif
    <title>{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts/irs/index.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">
    <main class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center gap-4 p-6 text-center">
        <h1 class="text-xl font-bold">{{ config('app.name') }}</h1>

        <div
            class="w-full space-y-3 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm"
            data-attempt-status="{{ $status->value }}"
        >
            @if ($status === PaymentAttemptStatus::PAID)
                <p class="text-lg font-semibold text-green-700">{{ __('order::strings.Payment Received') }}</p>
                <p>{{ __('order::strings.Payment Received Text') }}</p>
            @elseif ($status === PaymentAttemptStatus::PENDING)
                <p class="text-lg font-semibold text-amber-700">{{ __('order::strings.Payment Confirming') }}</p>
                <p>{{ __('order::strings.Payment Confirming Text') }}</p>
            @else
                <p class="text-lg font-semibold text-red-700">{{ __('order::strings.Payment Not Completed') }}</p>
                <p>{{ __('order::strings.Payment Not Completed Text') }}</p>
            @endif

            <dl class="space-y-1 border-t border-zinc-100 pt-3 text-sm text-zinc-600">
                <div class="flex justify-between"><dt>{{ __('order::attributes.Order Number') }}</dt><dd dir="ltr">{{ $attempt->order->order_number }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('order::attributes.Amount') }}</dt><dd>{{ Utils::pDigits(Utils::formatQuantity($attempt->amount, 2, ',')) }}</dd></div>
                @if ($status === PaymentAttemptStatus::PAID && $attempt->result_reference)
                    <div class="flex justify-between"><dt>{{ __('order::strings.Tracking Number') }}</dt><dd dir="ltr">{{ $attempt->result_reference }}</dd></div>
                @endif
            </dl>
        </div>

        <p class="text-xs text-zinc-500">{{ __('order::strings.You Can Close This Page') }}</p>
    </main>
</body>
</html>
