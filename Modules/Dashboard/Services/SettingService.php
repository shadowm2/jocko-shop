<?php

namespace Modules\Dashboard\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Modules\Dashboard\Models\Setting;

/**
 * Reads and writes the settings declared in config('dashboard.settings').
 *
 * ponytail: one SELECT per request. Add a Cache::rememberForever (forgotten
 * in set/forget) if the table grows or the cache moves off the database.
 */
#[Singleton]
class SettingService
{
    /** @var array<string, string>|null */
    private ?array $values = null;

    /**
     * Declared fields keyed by "group.field".
     *
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        $fields = [];

        foreach (config('dashboard.settings', []) as $group => $definition) {
            foreach ($definition['fields'] as $name => $field) {
                $fields["{$group}.{$name}"] = $field + ['group' => $group, 'name' => $name];
            }
        }

        return $fields;
    }

    /**
     * Stored values keyed by "group.field". Blank values are never stored.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->values ??= $this->load();
    }

    public function set(string $key, ?string $value): void
    {
        if (blank($value)) {
            $this->forget($key);

            return;
        }

        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        $this->values = null;
    }

    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();
        $this->values = null;
    }

    /**
     * Let stored values override the matching config() entries so packages
     * keep reading config as usual. Unset fields keep their .env value.
     * Reads the table afresh, so a queue worker can call it again per job.
     */
    public function applyToConfig(): void
    {
        $this->values = null;

        foreach ($this->fields() as $key => $field) {
            if (isset($field['config'])) {
                $value = $this->all()[$key] ?? null;
                if ($value !== null) {
                    config([$field['config'] => $value]);
                }
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function load(): array
    {
        $values = [];

        try {
            foreach (Setting::all() as $setting) {
                try {
                    $values[$setting->key] = $setting->value;
                } catch (DecryptException) {
                    // APP_KEY changed since this was saved: treat it as unset
                    // instead of taking every request down.
                }
            }
        } catch (QueryException) {
            // No table yet (before the first migrate) or the database is down.
        }

        return array_filter($values, static fn ($value) => filled($value));
    }
}
