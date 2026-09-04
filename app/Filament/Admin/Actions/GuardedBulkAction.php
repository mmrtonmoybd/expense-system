<?php

declare(strict_types=1);

namespace App\Filament\Admin\Actions;

use App\Models\User;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * A bulk action that only touches the records it is actually allowed to touch.
 *
 * A selection is rarely uniform: a list of sales holds drafts next to posted ones, a list of
 * categories holds some that are still in use. Rather than failing the whole batch or, worse,
 * half-applying it, every record is checked against an eligibility rule first, each one runs
 * on its own so a single failure cannot roll back the rest, and the operator is told exactly
 * how many were changed, skipped and rejected.
 */
final class GuardedBulkAction
{
    /**
     * @param  Closure(Model): bool  $eligible  whether this record may be processed
     * @param  Closure(Model, User, array<string, mixed>): void  $handle  the work itself
     * @param  array<int, mixed>  $schema  extra fields to collect before running
     */
    public static function make(
        string $name,
        string $label,
        Heroicon $icon,
        string $color,
        Closure $eligible,
        Closure $handle,
        ?string $ability = null,
        string $skipReason = 'not eligible',
        array $schema = [],
        ?string $confirmation = null,
    ): BulkAction {
        $permitted = fn (): bool => $ability === null || (auth()->user()?->can($ability) ?? false);

        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->schema($schema)
            ->requiresConfirmation()
            ->modalDescription($confirmation)
            ->deselectRecordsAfterCompletion()
            ->visible($permitted)
            ->action(function (Collection $records, array $data) use ($permitted, $eligible, $handle, $label, $skipReason): void {
                abort_unless($permitted(), 403);

                $user = auth()->user();
                abort_unless($user instanceof User, 403);

                $done = 0;
                $skipped = 0;
                $failures = [];

                foreach ($records as $record) {
                    if (! $eligible($record)) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $handle($record, $user, $data);
                        $done++;
                    } catch (Throwable $exception) {
                        $failures[] = $exception->getMessage();
                    }
                }

                $body = collect([
                    $skipped > 0 ? "{$skipped} skipped ({$skipReason})" : null,
                    $failures === [] ? null : count($failures).' failed: '.collect($failures)->unique()->take(3)->implode(' '),
                ])->filter()->implode(' · ');

                Notification::make()
                    ->title("{$label}: {$done} of ".$records->count().' processed')
                    ->body($body === '' ? null : $body)
                    ->status($failures !== [] ? 'warning' : ($done > 0 ? 'success' : 'warning'))
                    ->send();
            });
    }
}
