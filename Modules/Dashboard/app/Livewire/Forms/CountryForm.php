<?php

namespace Modules\Dashboard\Livewire\Forms;

use Livewire\Form;
use Modules\Dashboard\Models\Country;
use Modules\Dashboard\Services\CountryService;

class CountryForm extends Form
{
    public ?int $id = null;

    public string $name;

    public string $code;

    public ?string $code3;

    public ?string $phone_code = null;

    public ?bool $is_active = true;

    /** @var array<mixed, string|list<string>> */
    protected array $rules = [
        'name' => 'required|string',
        'code' => 'required|string',
        'code3' => 'nullable|string',
        'phone_code' => ['nullable', 'string', 'max:10', 'regex:/^\+\d{1,4}(-\d{1,4})?$/'],
        'is_active' => 'nullable|boolean',
    ];

    public function save(): void
    {
        $data = $this->validate();

        $countryService = resolve(CountryService::class);

        if ($this->id) {
            $countryService->update($this->id, $data);
        } else {
            $countryService->create($data);
        }
    }

    public function setCountry(Country $country): void
    {
        $this->id = $country->id;
        $this->name = $country->name;
        $this->code = $country->code;
        $this->code3 = $country->code3;
        $this->phone_code = $country->phone_code;
        $this->is_active = $country->is_active;
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('dashboard::attributes.Country Name'),
            'code' => __('dashboard::attributes.Country Code'),
            'code3' => __('dashboard::attributes.Country Code3'),
            'phone_code' => __('dashboard::attributes.Country Phone Code'),
        ];
    }
}
