<?php

declare(strict_types=1);

namespace App\Filament\Admin\Filters;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;

/** A minimum/maximum window over a numeric column. */
final class NumberRangeFilter
{
    public static function make(string $column, ?string $label = null, ?string $prefix = null): Filter
    {
        $label ??= str($column)->headline()->toString();
        $minKey = "{$column}_min";
        $maxKey = "{$column}_max";

        return Filter::make($column)
            ->label($label)
            ->schema([
                TextInput::make($minKey)->label('Minimum')->numeric()->inputMode('decimal')->prefix($prefix),
                TextInput::make($maxKey)->label('Maximum')->numeric()->inputMode('decimal')->prefix($prefix),
            ])
            ->columns(['default' => 1, 'sm' => 2])
            ->columnSpan(['default' => 1, 'sm' => 2])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when(filled($data[$minKey] ?? null), fn (Builder $query): Builder => $query->where($column, '>=', $data[$minKey]))
                ->when(filled($data[$maxKey] ?? null), fn (Builder $query): Builder => $query->where($column, '<=', $data[$maxKey])))
            ->indicateUsing(function (array $data) use ($label, $minKey, $maxKey): array {
                $indicators = [];

                if (filled($data[$minKey] ?? null)) {
                    $indicators[] = Indicator::make("{$label} from ".number_format((float) $data[$minKey], 2))->removeField($minKey);
                }

                if (filled($data[$maxKey] ?? null)) {
                    $indicators[] = Indicator::make("{$label} up to ".number_format((float) $data[$maxKey], 2))->removeField($maxKey);
                }

                return $indicators;
            });
    }
}
