<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $dateFormat = (string) config('app.date_format');
        $timeFormat = (string) config('app.time_format');
        $dateTimeFormat = (string) config('app.datetime_format');
        $dateTimeSecondsFormat = (string) config('app.datetime_seconds_format');

        FilamentTimezone::set((string) config('app.timezone'));
        // Every table gets the same filtering behaviour: filters live in a modal, apply as
        // soon as they change so the summary widgets follow along, and survive a page reload.
        Table::configureUsing(fn (Table $table): Table => $table
            ->defaultDateDisplayFormat($dateFormat)
            ->defaultDateTimeDisplayFormat($dateTimeFormat)
            ->defaultTimeDisplayFormat($timeFormat)
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'xl' => 3])
            ->filtersFormWidth(Width::FiveExtraLarge)
            ->filtersFormMaxHeight('32rem')
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistSortInSession());
        Schema::configureUsing(fn (Schema $schema): Schema => $schema
            ->defaultDateDisplayFormat($dateFormat)
            ->defaultDateTimeDisplayFormat($dateTimeFormat)
            ->defaultTimeDisplayFormat($timeFormat));
        DatePicker::configureUsing(fn (DatePicker $picker): DatePicker => $picker
            ->native(false)
            ->defaultDateDisplayFormat($dateFormat));
        DateTimePicker::configureUsing(fn (DateTimePicker $picker): DateTimePicker => $picker
            ->native(false)
            ->seconds(false)
            ->defaultDateDisplayFormat($dateFormat)
            ->defaultDateTimeDisplayFormat($dateTimeFormat)
            ->defaultDateTimeWithSecondsDisplayFormat($dateTimeSecondsFormat)
            ->defaultTimeDisplayFormat($timeFormat)
            ->defaultTimeWithSecondsDisplayFormat('h:i:s A'));
        TimePicker::configureUsing(fn (TimePicker $picker): TimePicker => $picker
            ->native(false)
            ->seconds(false)
            ->defaultTimeDisplayFormat($timeFormat)
            ->defaultTimeWithSecondsDisplayFormat('h:i:s A'));

        Model::preventLazyLoading(! app()->isProduction());
        Page::formActionsAlignment(Alignment::Right);
        Notifications::alignment(Alignment::End);
        Notifications::verticalAlignment(VerticalAlignment::End);
        Page::$reportValidationErrorUsing = function (ValidationException $exception): void {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();
        };
    }
}
