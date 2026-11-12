@php
    use App\Helpers\Utils;
    use Morilog\Jalali\Jalalian;

    $money = fn ($value) => Utils::formatQuantity($value, 2, ',');
@endphp
<div class="mx-auto max-w-3xl space-y-6 p-8 text-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
            <p class="text-zinc-500">{{ __('order::strings.Quote') }}</p>
            @if (filled(config('shop.address')))
                <p class="text-zinc-500">{{ config('shop.address') }}</p>
            @endif
            @if (filled(config('shop.phone')))
                <p class="text-zinc-500">{{ Utils::pDigits(config('shop.phone')) }}</p>
            @endif
        </div>
        <div class="space-y-1 text-end">
            <div class="font-semibold"><span dir="ltr">{{ $quote->quote_number }}</span></div>
            <div>{{ Utils::pDigits(Jalalian::fromDateTime($quote->created_at)->format('Y/m/d')) }}</div>
            @if ($quote->valid_until)
                <div>{{ __('order::strings.Quote Valid Until', ['date' => Utils::pDigits(Jalalian::fromDateTime($quote->valid_until)->format('Y/m/d'))]) }}</div>
            @endif
            <flux:button class="print:hidden" icon="printer" size="sm" onclick="window.print()">{{ __('order::strings.Print') }}</flux:button>
        </div>
    </div>

    <dl class="grid grid-cols-2 gap-x-6 gap-y-2 rounded-lg border border-zinc-200 p-4">
        <div>
            <dt class="text-zinc-500">{{ __('order::strings.Customer') }}</dt>
            <dd class="font-medium">{{ $quote->user->fullName() }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('order::strings.Mobile') }}</dt>
            <dd class="font-medium">{{ Utils::pDigits($quote->user->mobile) }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('order::attributes.User Car') }}</dt>
            <dd class="font-medium">{{ $quote->userCar->car->name }}</dd>
        </div>
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
            @foreach ($quote->items as $line)
                <tr class="border-b border-zinc-200">
                    <td class="py-2">{{ $line->item->name }}</td>
                    <td class="py-2">{{ Utils::formatQuantity($line->quantity) }} {{ $line->unit?->symbol }}</td>
                    <td class="py-2">{{ $money($line->unit_price) }}</td>
                    <td class="py-2 text-end">{{ $money($line->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="ms-auto w-full max-w-xs">
        <div class="flex justify-between border-t border-zinc-300 pt-1 text-base font-bold"><span>{{ __('order::attributes.Total') }}</span><span>{{ $money($total) }}</span></div>
    </div>

    @if (filled($quote->description))
        <p class="text-zinc-500">{{ $quote->description }}</p>
    @endif

    <p class="text-xs text-zinc-500">{{ __('order::strings.Quote Footer') }}</p>
</div>
