@props([
    'image' => null,
    'alt' => 'alt',
    'initial' => '',
    'name' => '',
])

@php
    $src = $image instanceof \Modules\Dashboard\Models\Media ? $image->publicPath() : null;

    $modalName = 'image-preview-' . uniqid();
@endphp

@if ($src)
    <div wire:key="{{ $modalName }}">
        <button
            type="button"
            onclick="Flux.modal('{{ $modalName }}').show()"
            class="size-28 group relative block overflow-hidden rounded-xl focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2"
        >
            <img
                src="{{ $src }}"
                alt="{{ $alt }}"
                class="mx-auto object-contain max-h-full max-w-full rounded-xl shadow-sm transition duration-200 group-hover:scale-105 group-hover:opacity-90"
            />

            <div
                class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/0 transition group-hover:bg-black/20">
                <flux:icon.magnifying-glass
                    class="size-5 text-white opacity-0 drop-shadow transition group-hover:opacity-100"
                />
            </div>
        </button>

        <flux:modal
            name="{{ $modalName }}"
            class="max-w-2xl"
        >
            <div class="flex flex-col items-start justify-center p-5 gap-2">
                <flux:heading size="lg">
                    <!-- TODO: Change this !-->
                    {{ __('car::attributes.Car Company Name') }}:
                    <span class="font-bold text-lg">
                        {{ $name }}
                    </span>
                </flux:heading>
                <img
                    src="{{ $src }}"
                    alt="{{ $alt }}"
                    class="max-h-[60vh] max-w-full rounded-xl object-contain my-5"
                />
                <flux:modal.close>
                    <flux:button variant="danger">
                        {{ __('dashboard::strings.Close') }}
                    </flux:button>
                </flux:modal.close>
            </div>
        </flux:modal>
    </div>
@else
    <div
        class="flex h-20 w-24 items-center justify-center rounded-xl bg-zinc-100 text-sm font-medium text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
        {{ $initial ?: $name ?: '?' }}
    </div>
@endif
