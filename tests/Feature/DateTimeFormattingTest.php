<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Sale;
use App\Models\User;
use App\Services\ReportDataBuilder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;

uses(LazilyRefreshDatabase::class);

test('application uses the requested human readable date and twelve hour time formats', function () {
    expect(config('app.date_format'))->toBe('d M, Y')
        ->and(config('app.time_format'))->toBe('h:i A')
        ->and(config('app.datetime_format'))->toBe('d M, Y h:i A')
        ->and(Carbon::parse('2026-08-14 18:30:00')->format(config('app.datetime_format')))->toBe('14 Aug, 2026 06:30 PM');
});

test('filament date and time pickers use the global non-native display formats', function () {
    $date = DatePicker::make('date');
    $dateTime = DateTimePicker::make('date_time');
    $time = TimePicker::make('time');

    expect($date->getDisplayFormat())->toBe('d M, Y')
        ->and($date->isNative())->toBeFalse()
        ->and($dateTime->getDisplayFormat())->toBe('d M, Y h:i A')
        ->and($dateTime->isNative())->toBeFalse()
        ->and($time->getDisplayFormat())->toBe('h:i A')
        ->and($time->isNative())->toBeFalse();
});

test('reports format dates and audit times before every export format receives them', function () {
    Sale::factory()->create([
        'sale_date' => '2026-08-14',
        'status' => 'posted',
    ]);
    $user = User::factory()->create();
    AuditLog::query()->create([
        'user_id' => $user->id,
        'action' => 'format_test',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
        'occurred_at' => '2026-08-14 18:30:00',
    ]);

    $builder = app(ReportDataBuilder::class);
    $sales = $builder->build(['report' => 'sales', 'from' => '2026-08-14', 'to' => '2026-08-14']);
    $audit = $builder->build(['report' => 'audit', 'from' => '2026-08-14', 'to' => '2026-08-14']);

    expect($sales['rows'][0][1])->toBe('14 Aug, 2026')
        ->and($audit['rows'][0][0])->toBe('14 Aug, 2026 06:30 PM');
});
