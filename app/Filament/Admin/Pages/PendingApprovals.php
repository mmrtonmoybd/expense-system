<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\PostSaleAction;
use App\Actions\RejectSaleAction;
use App\Filament\Admin\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Support\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

final class PendingApprovals extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Clock;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.admin.pages.pending-approvals';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:PendingApprovals') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! self::canAccess()) {
            return null;
        }

        $count = Sale::query()->where('status', 'pending')->count();

        return $count ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table->query(Sale::query()->with('customer')->where('status', 'pending'))->columns([
            TextColumn::make('sale_number')->searchable(), TextColumn::make('customer.name')->description(fn (Sale $record): string => $record->customer->phone), TextColumn::make('sale_date')->date(), TextColumn::make('total_usd')->label('USD')->numeric(decimalPlaces: 2), TextColumn::make('total_bdt_revenue')->label('BDT')->money('BDT'), TextColumn::make('creator.name')->label('Created by'), TextColumn::make('submitted_at')->dateTime(),
        ])->recordActions([
            Action::make('view')->url(fn (Sale $record): string => SaleResource::getUrl('view', ['record' => $record])),
            Action::make('approve')->color('success')->requiresConfirmation()->visible(fn (): bool => auth()->user()?->can('Approve:Sale') ?? false)->action(function (Sale $record): void {
                abort_unless(auth()->user()?->can('Approve:Sale'), 403);
                $sale = app(PostSaleAction::class)->execute($record, auth()->user());
                app(AuditLogger::class)->record(auth()->user(), 'sale_approved', $sale);
                Notification::make()->title('Sale approved and posted')->success()->send();
            }),
            Action::make('reject')->color('danger')->schema([Textarea::make('reason')->required()])->visible(fn (): bool => auth()->user()?->can('Reject:Sale') ?? false)->action(function (Sale $record, array $data): void {
                abort_unless(auth()->user()?->can('Reject:Sale'), 403);
                app(RejectSaleAction::class)->execute($record, auth()->user(), $data['reason']);
                Notification::make()->title('Sale rejected')->success()->send();
            }),
        ]);
    }
}
