<?php

use Livewire\Livewire;
use Modules\Dashboard\Livewire\CountryCreate;
use Modules\Dashboard\Livewire\CountryEdit;
use Modules\Dashboard\Livewire\CountryList;
use Modules\Dashboard\Models\Country;
use Modules\User\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->country = Country::forceCreate(['name' => 'Iran', 'code' => 'IR', 'code3' => 'IRN', 'phone_code' => '+98', 'is_active' => false]);
});

it('shows the phone code in the list and finds a country by it', function () {
    Country::forceCreate(['name' => 'Azerbaijan', 'code' => 'AZ', 'code3' => 'AZE', 'phone_code' => '+994']);

    Livewire::test(CountryList::class)
        ->assertSee(__('dashboard::attributes.Country Phone Code'))
        ->assertSee('+۹۸')
        ->set('filters.search', '994')
        ->assertSee('AZE')
        ->assertDontSee('IRN');
});

it('loads the phone code and keeps an inactive country inactive when editing', function () {
    Livewire::test(CountryEdit::class, ['country' => $this->country])
        ->assertSet('form.phone_code', '+98')
        ->assertSet('form.is_active', false)
        ->set('form.phone_code', '+1-268')
        ->call('save')
        ->assertHasNoErrors();

    $this->country->refresh();
    expect($this->country->phone_code)->toBe('+1-268')->and((bool) $this->country->is_active)->toBeFalse();
});

it('saves a phone code on a new country and allows leaving it empty', function () {
    Livewire::test(CountryCreate::class)
        ->set('form.name', 'Turkey')->set('form.code', 'TR')->set('form.code3', 'TUR')->set('form.phone_code', '+90')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(CountryCreate::class)
        ->set('form.name', 'Atlantis')->set('form.code', 'AT')->set('form.code3', 'ATL')
        ->call('save')
        ->assertHasNoErrors();

    expect(Country::where('code', 'TR')->value('phone_code'))->toBe('+90')
        ->and(Country::where('code', 'AT')->value('phone_code'))->toBeNull();
});

it('rejects a phone code that is not a plus sign and digits', function (string $value) {
    Livewire::test(CountryEdit::class, ['country' => $this->country])
        ->set('form.phone_code', $value)
        ->call('save')
        ->assertHasErrors('form.phone_code');
})->with(['98', '+98 21', '+abc', '+123456', '++98']);
