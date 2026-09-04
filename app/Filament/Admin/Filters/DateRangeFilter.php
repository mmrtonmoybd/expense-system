<?php

declare(strict_types=1);

namespace App\Filament\Admin\Filters;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * A from/until window over a date or timestamp column, with the presets operators reach for
 * most often. Choosing a preset fills the two pickers so the range stays visible and editable.
 */
final class DateRangeFilter
{
    /** @return array<string, string> */
    public static function presets(): array
    {
        return [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This week',
            'last_week' => 'Last week',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_quarter' => 'This quarter',
            'this_year' => 'This year',
            'last_year' => 'Last year',
            'last_7' => 'Last 7 days',
            'last_30' => 'Last 30 days',
            'last_90' => 'Last 90 days',
        ];
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    public static function resolvePreset(string $preset): ?array
    {
        return match ($preset) {
            'today' => [today(), today()],
            'yesterday' => [today()->subDay(), today()->subDay()],
            'this_week' => [today()->startOfWeek(), today()->endOfWeek()],
            'last_week' => [today()->subWeek()->startOfWeek(), today()->subWeek()->endOfWeek()],
            'this_month' => [today()->startOfMonth(), today()->endOfMonth()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [today()->startOfQuarter(), today()->endOfQuarter()],
            'this_year' => [today()->startOfYear(), today()->endOfYear()],
            'last_year' => [today()->subYear()->startOfYear(), today()->subYear()->endOfYear()],
            'last_7' => [today()->subDays(6), today()],
            'last_30' => [today()->subDays(29), today()],
            'last_90' => [today()->subDays(89), today()],
            default => null,
        };
    }

    public static function make(string $column, ?string $label = null, bool $timestamp = false): Filter
    {
        $label ??= str($column)->headline()->toString();
        $fromKey = "{$column}_from";
        $untilKey = "{$column}_until";

        return Filter::make($column)
            ->label($label)
            ->schema([
                Select::make('preset')
                    ->label("{$label} preset")
                    ->placeholder('Custom range')
                    ->options(self::presets())
                    ->live()
                    ->afterStateUpdated(function (?string $state, callable $set) use ($fromKey, $untilKey): void {
                        $range = $state === null ? null : self::resolvePreset($state);

                        $set($fromKey, $range === null ? null : $range[0]->toDateString());
                        $set($untilKey, $range === null ? null : $range[1]->toDateString());
                    }),
                DatePicker::make($fromKey)->label('From')->native(false)->maxDate(fn (callable $get): mixed => $get($untilKey)),
                DatePicker::make($untilKey)->label('Until')->native(false)->minDate(fn (callable $get): mixed => $get($fromKey)),
            ])
            ->columns(['default' => 1, 'sm' => 3])
            ->columnSpanFull()
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data[$fromKey] ?? null, fn (Builder $query, string $date): Builder => $timestamp
                    ? $query->where($column, '>=', Carbon::parse($date)->startOfDay())
                    : $query->whereDate($column, '>=', $date))
                ->when($data[$untilKey] ?? null, fn (Builder $query, string $date): Builder => $timestamp
                    ? $query->where($column, '<=', Carbon::parse($date)->endOfDay())
                    : $query->whereDate($column, '<=', $date)))
            ->indicateUsing(function (array $data) use ($label, $fromKey, $untilKey): array {
                $indicators = [];

                if (filled($data[$fromKey] ?? null)) {
                    $indicators[] = Indicator::make($label.' from '.Carbon::parse($data[$fromKey])->format((string) config('app.date_format')))->removeField($fromKey);
                }

                if (filled($data[$untilKey] ?? null)) {
                    $indicators[] = Indicator::make($label.' until '.Carbon::parse($data[$untilKey])->format((string) config('app.date_format')))->removeField($untilKey);
                }

                return $indicators;
            });
    }
}
