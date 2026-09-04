<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * The filter state of the Business Analytics page, resolved once and shared by every chart
 * on it so they all describe the same slice of the business.
 */
final readonly class AnalyticsFilters
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public string $granularity,
        public ?int $customerId,
        public ?int $platformId,
    ) {}

    /** @param array<string, mixed>|null $filters */
    public static function fromPage(?array $filters): self
    {
        $from = filled($filters['from'] ?? null) ? Carbon::parse((string) $filters['from']) : today()->startOfMonth()->subMonths(5);
        $to = filled($filters['to'] ?? null) ? Carbon::parse((string) $filters['to']) : today();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        return new self(
            from: $from->startOfDay(),
            to: $to->startOfDay(),
            granularity: in_array($filters['granularity'] ?? null, ['day', 'week', 'month'], true) ? (string) $filters['granularity'] : 'month',
            customerId: filled($filters['customer_id'] ?? null) ? (int) $filters['customer_id'] : null,
            platformId: filled($filters['platform_id'] ?? null) ? (int) $filters['platform_id'] : null,
        );
    }

    /** @return array<int, Carbon> the first day of every period in the range */
    public function periods(): array
    {
        $cursor = $this->periodStart($this->from);
        $periods = [];

        while ($cursor->lessThanOrEqualTo($this->to) && count($periods) < 400) {
            $periods[] = $cursor->copy();
            $cursor = match ($this->granularity) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                default => $cursor->addMonthNoOverflow(),
            };
        }

        return $periods;
    }

    public function periodStart(Carbon $date): Carbon
    {
        return match ($this->granularity) {
            'day' => $date->copy()->startOfDay(),
            'week' => $date->copy()->startOfWeek(),
            default => $date->copy()->startOfMonth(),
        };
    }

    public function periodEnd(Carbon $start): Carbon
    {
        return match ($this->granularity) {
            'day' => $start->copy()->endOfDay(),
            'week' => $start->copy()->endOfWeek(),
            default => $start->copy()->endOfMonth(),
        };
    }

    public function periodLabel(Carbon $start): string
    {
        return match ($this->granularity) {
            'day' => $start->format((string) config('app.date_format')),
            'week' => 'w/c '.$start->format((string) config('app.date_format')),
            default => $start->format('M Y'),
        };
    }

    /** Restrict a query that joins `sales` to the selected customer and platform. */
    public function applyToSales(Builder $query): Builder
    {
        return $query
            ->when($this->customerId, fn (Builder $query, int $id): Builder => $query->where('sales.customer_id', $id))
            ->when($this->platformId, fn (Builder $query, int $id): Builder => $query->whereExists(
                fn (Builder $sub): Builder => $sub->selectRaw('1')->from('sale_lines')->whereColumn('sale_lines.sale_id', 'sales.id')->where('sale_lines.platform_id', $id),
            ));
    }

    public function label(): string
    {
        $format = (string) config('app.date_format');

        return $this->from->format($format).' – '.$this->to->format($format);
    }
}
