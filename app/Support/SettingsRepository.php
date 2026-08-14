<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

final class SettingsRepository
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::query()->where('key', $key)->value('value') ?? $default;
    }

    /** @param array<string, mixed> $settings */
    public function putMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]);
        }
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOL);
    }
}
