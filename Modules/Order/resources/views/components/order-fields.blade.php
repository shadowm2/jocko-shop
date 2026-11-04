@if (!isset($order))
    <flux:field>
        <flux:label>
            {{ __('order::attributes.User Name') }}
        </flux:label>

        <flux:select wire:model.live="form.user_id">

            <flux:select.option value="">
                {{ __('order::strings.Select User') }}
            </flux:select.option>

            @foreach ($users as $user)
                <flux:select.option :value="$user->id">
                    {{ $user->fullName() }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:error name="form.user_id" />
    </flux:field>
@endif
<flux:field>
    <flux:label>
        {{ __('order::attributes.User Car') }}
    </flux:label>

    <flux:select wire:model.live="form.user_car_id">

        <flux:select.option value="">
            {{ __('order::strings.Select User Car') }}
        </flux:select.option>

        @foreach ($userCars as $userCar)
            <flux:select.option :value="$userCar->id">
                {{ $userCar->car->name }}
            </flux:select.option>
        @endforeach

    </flux:select>

    <flux:error name="form.user_car_id" />
</flux:field>

<flux:field>
    <flux:label>
        {{ __('order::attributes.Order Status') }}
    </flux:label>

    <flux:select wire:model="form.status">
        @foreach (\Modules\Order\Enums\OrderStatus::cases() as $status)
            <flux:select.option :value="$status->value">
                {{ $status->label() }}
            </flux:select.option>
        @endforeach
    </flux:select>

    <flux:error name="form.status" />
</flux:field>

<x-order::order-items-editor
    :form="$this->form"
    :$itemCatalog
/>

<x-order::order-services-editor :form="$this->form" />

<div class="grid gap-4 sm:grid-cols-2">
    <flux:field>
        <flux:label>{{ __('order::attributes.Odometer') }}</flux:label>
        <flux:input wire:model="form.odometer" inputmode="numeric" :placeholder="__('order::strings.Odometer Hint')" />
        <flux:error name="form.odometer" />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('order::attributes.Next Service') }}</flux:label>
        <input
            class="persian-datepicker
                        appearance-none [:where(&)]:w-full ps-3 pe-10 block h-10 py-2 text-base sm:text-sm leading-[1.375rem]
                        rounded-lg shadow-xs border bg-white dark:bg-white/10 dark:disabled:bg-white/[7%]
                        text-zinc-700 dark:text-zinc-300 disabled:text-zinc-500 dark:disabled:text-zinc-400
                        dark:[&>option]:bg-zinc-700 dark:[&>option]:text-white disabled:shadow-none border
                        border-zinc-200 border-b-zinc-300/80 dark:border-white/10"
            wire:model="form.next_service_at"
            value="{{ $this->form->next_service_at_gregorian }}"
            autocomplete="off"
        />
        <flux:description>{{ __('order::strings.Next Service Hint') }}</flux:description>
        <flux:error name="form.next_service_at" />
    </flux:field>
</div>

<flux:field>
    <flux:label>
        {{ __('order::attributes.Description') }}
    </flux:label>
    <flux:textarea
        wire:model="form.description"
        :placeholder="__('order::strings.Enter order description')"
    />
    <flux:error name="form.description" />
</flux:field>
