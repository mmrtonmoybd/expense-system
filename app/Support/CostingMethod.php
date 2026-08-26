<?php

declare(strict_types=1);

namespace App\Support;

enum CostingMethod: string
{
    case Average = 'average';
    case Fifo = 'fifo';
    case Lifo = 'lifo';

    public const string SettingKey = 'usd_costing_method';

    public static function default(): self
    {
        return self::Average;
    }

    public static function fromNullable(mixed $value): self
    {
        return self::tryFrom(is_string($value) ? mb_strtolower(mb_trim($value)) : '') ?? self::default();
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $method): array => [$method->value => $method->label()])
            ->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::Average => 'Moving Weighted Average',
            self::Fifo => 'FIFO',
            self::Lifo => 'LIFO',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Average => 'Average',
            self::Fifo => 'FIFO',
            self::Lifo => 'LIFO',
        };
    }

    public function usesLayers(): bool
    {
        return $this !== self::Average;
    }
}
