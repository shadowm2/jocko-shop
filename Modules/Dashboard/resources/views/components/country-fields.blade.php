<flux:field>
    <flux:label>
        {{ __('dashboard::attributes.Country Name') }}
    </flux:label>
    <flux:input
        :placeholder="__('dashboard::strings.Enter Country Name')"
        wire:model="form.name"
    />
    <flux:error name="form.name" />
</flux:field>

<flux:field>
    <flux:label>
        {{ __('dashboard::attributes.Country Code') }}
    </flux:label>
    <flux:input
        :placeholder="__('dashboard::strings.Enter Country Code')"
        minlength="2"
        maxlength="2"
        wire:model="form.code"
    />
    <flux:error name="form.code" />
</flux:field>

<flux:field>
    <flux:label>
        {{ __('dashboard::attributes.Country Code3') }}
    </flux:label>
    <flux:input
        :placeholder="__('dashboard::strings.Enter Country Code3')"
        minlength="3"
        maxlength="3"
        wire:model="form.code3"
    />
    <flux:error name="form.code3" />
</flux:field>

<flux:field>
    <flux:label>
        {{ __('dashboard::attributes.Country Phone Code') }}
    </flux:label>
    <flux:input
        :placeholder="__('dashboard::strings.Enter Country Phone Code')"
        maxlength="10"
        dir="ltr"
        wire:model="form.phone_code"
    />
    <flux:error name="form.phone_code" />
</flux:field>

<flux:field>
    <flux:label>
        {{ __('dashboard::attributes.Is Active') }}
    </flux:label>
    <flux:switch wire:model="form.is_active" />
    <flux:error name="form.is_active" />
</flux:field>
