<?php

declare(strict_types=1);

use App\Actions\PostSaleAction;
use App\Actions\PostUsdPurchaseAction;
use App\Models\Customer;
use App\Models\CustomerPlatform;
use App\Models\Invoice;
use App\Models\Platform;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Setting;
use App\Models\UsdPurchase;
use App\Models\User;
use App\Support\Localization;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;

uses(LazilyRefreshDatabase::class);

function localizedInvoice(User $user): Invoice
{
    app(PostUsdPurchaseAction::class)->execute(
        UsdPurchase::factory()->create(['usd_quantity' => '1000', 'purchase_rate' => '100']),
        $user,
    );

    $customer = Customer::factory()->create(['name' => 'Padma Pharmacy']);
    $platform = Platform::factory()->create(['name' => 'Facebook']);
    $sale = Sale::factory()->create(['customer_id' => $customer->id, 'created_by' => $user->id]);

    CustomerPlatform::query()->create([
        'customer_id' => $customer->id,
        'platform_id' => $platform->id,
        'current_selling_rate' => '123.45',
    ]);
    SaleLine::query()->create([
        'sale_id' => $sale->id,
        'platform_id' => $platform->id,
        'usd_quantity' => '250',
        'selling_rate' => '123.45',
        'weighted_cost_rate' => '0',
        'revenue' => '0',
        'inventory_cost' => '0',
        'gross_profit' => '0',
    ]);

    $posted = app(PostSaleAction::class)->execute($sale, $user);

    return Invoice::query()->whereBelongsTo($posted)->sole();
}

test('numbers and dates are rewritten in bengali numerals', function () {
    App::setLocale('en');

    expect(Localization::number('12345.67'))->toBe('12,345.67')
        ->and(Localization::money('12345.67'))->toBe('৳ 12,345.67')
        ->and(Localization::date(Carbon::parse('2026-01-15'), 'd M, Y'))->toBe('15 Jan, 2026');

    App::setLocale('bn');

    expect(Localization::number('12345.67'))->toBe('১২,৩৪৫.৬৭')
        ->and(Localization::money('12345.67'))->toBe('৳ ১২,৩৪৫.৬৭')
        ->and(Localization::digits('2026'))->toBe('২০২৬')
        ->and(Localization::isBengali())->toBeTrue()
        ->and(Localization::tag())->toBe('bn-BD');
});

test('an unknown locale falls back to english', function () {
    expect(Localization::isSupported('fr'))->toBeFalse()
        ->and(Localization::isSupported('bn'))->toBeTrue()
        ->and(Localization::isSupported(null))->toBeFalse();

    App::setLocale('fr');

    expect(Localization::current())->toBe('en');
});

test('the business default locale drives the panel and the documents', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');
    Setting::query()->create(['key' => Localization::SettingKey, 'value' => 'bn']);

    $invoice = localizedInvoice($user);

    $this->actingAs($user)->get(route('invoices.print', $invoice))
        ->assertOk()
        ->assertSee('চালান')
        ->assertSee('গ্রাহক')
        ->assertSee('সর্বমোট')
        ->assertSee('২৫০.০০')
        ->assertDontSee('Billed to');
});

test('the lang parameter prints one document in the other language', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');

    $invoice = localizedInvoice($user);

    $this->actingAs($user)->get(route('invoices.print', $invoice))
        ->assertOk()
        ->assertSee('Billed to');

    $this->actingAs($user)->get(route('invoices.print', ['invoice' => $invoice, 'lang' => 'bn']))
        ->assertOk()
        ->assertSee('গ্রাহক')
        ->assertSee('চালান');

    // The choice sticks for the session, so the next print stays Bengali.
    $this->actingAs($user)->get(route('invoices.print', $invoice))
        ->assertOk()
        ->assertSee('গ্রাহক');
});

test('a bengali invoice pdf embeds the bengali font and keeps its text', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');

    $invoice = localizedInvoice($user);

    $response = $this->actingAs($user)->get(route('invoices.pdf', ['invoice' => $invoice, 'lang' => 'bn']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $path = tempnam(sys_get_temp_dir(), 'inv').'.pdf';
    file_put_contents($path, $response->getContent());

    $text = shell_exec('pdftotext '.escapeshellarg($path).' - 2>/dev/null') ?? '';
    @unlink($path);

    // The PDF carries the decomposed two-part vowels, so compose the extracted text back
    // before comparing it with what the translation file says.
    $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;

    expect($text)->toContain('চালান')
        ->and($text)->toContain('গ্রাহক')
        ->and($text)->toContain('সর্বমোট')
        ->and($text)->toContain('২৫০.০০');
})->skip(fn (): bool => blank(shell_exec('command -v pdftotext')) || ! class_exists(Normalizer::class), 'pdftotext or ext-intl is not installed.');

test('reports print and export in bengali', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');
    localizedInvoice($user);

    $query = [
        'report' => 'sales',
        'from' => today()->startOfMonth()->toDateString(),
        'to' => today()->toDateString(),
        'lang' => 'bn',
    ];

    $this->actingAs($user)->get(route('reports.export', ['format' => 'print'] + $query))
        ->assertOk()
        ->assertSee('সময়কাল')
        ->assertSee('প্রিন্ট');

    $this->actingAs($user)->get(route('reports.export', ['format' => 'pdf'] + $query))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('the bengali font files ship with the application', function () {
    expect(file_exists(Localization::documentFontPath()))->toBeTrue()
        ->and(file_exists(Localization::documentFontPath('Bold')))->toBeTrue();
});

test('two part bengali vowels are split for the pdf engine', function () {
    // DomPDF cannot draw ো or ৌ around their consonant; it needs the two halves separately.
    expect(Localization::decomposeBengaliVowels('মোট'))->toBe("ম\u{09C7}\u{09BE}ট")
        ->and(Localization::decomposeBengaliVowels('মৌসুম'))->toBe("ম\u{09C7}\u{09D7}সুম")
        ->and(Localization::decomposeBengaliVowels('সর্বমোট'))->toBe("সর্বম\u{09C7}\u{09BE}ট")
        // Anything without a two-part vowel is left exactly as it was.
        ->and(Localization::decomposeBengaliVowels('গ্রাহক'))->toBe('গ্রাহক')
        ->and(Localization::decomposeBengaliVowels('Invoice #123'))->toBe('Invoice #123')
        // And it still means the same thing once composed back.
        ->and(Normalizer::normalize(Localization::decomposeBengaliVowels('সর্বমোট'), Normalizer::FORM_C))->toBe('সর্বমোট');
});

test('the pdf renderer applies the vowel fix to customer supplied text', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');

    app(PostUsdPurchaseAction::class)->execute(
        UsdPurchase::factory()->create(['usd_quantity' => '1000', 'purchase_rate' => '100']),
        $user,
    );

    // A name typed with the precomposed vowel, exactly as a Bengali keyboard produces it.
    $customer = Customer::factory()->create(['name' => 'মোহনা ট্রেডার্স']);
    $platform = Platform::factory()->create();
    $sale = Sale::factory()->create(['customer_id' => $customer->id, 'created_by' => $user->id]);
    CustomerPlatform::query()->create([
        'customer_id' => $customer->id,
        'platform_id' => $platform->id,
        'current_selling_rate' => '120',
    ]);
    SaleLine::query()->create([
        'sale_id' => $sale->id,
        'platform_id' => $platform->id,
        'usd_quantity' => '10',
        'selling_rate' => '120',
        'weighted_cost_rate' => '0',
        'revenue' => '0',
        'inventory_cost' => '0',
        'gross_profit' => '0',
    ]);
    $invoice = Invoice::query()->whereBelongsTo(app(PostSaleAction::class)->execute($sale, $user))->sole();

    $pdf = $this->actingAs($user)
        ->get(route('invoices.pdf', ['invoice' => $invoice, 'lang' => 'bn']))
        ->assertOk()
        ->getContent();

    $path = tempnam(sys_get_temp_dir(), 'inv').'.pdf';
    file_put_contents($path, $pdf);
    $text = Normalizer::normalize(shell_exec('pdftotext '.escapeshellarg($path).' - 2>/dev/null') ?? '', Normalizer::FORM_C);
    @unlink($path);

    expect($text)->toContain('মোহনা');
})->skip(fn (): bool => blank(shell_exec('command -v pdftotext')) || ! class_exists(Normalizer::class), 'pdftotext or ext-intl is not installed.');

test('the panel navigation and resource labels translate', function () {
    App::setLocale('en');

    expect(\App\Filament\Admin\Resources\Sales\SaleResource::getPluralModelLabel())->toBe('Sales')
        ->and(\App\Filament\Admin\Resources\Sales\SaleResource::getNavigationGroup())->toBe('Sales')
        ->and(\App\Filament\Admin\Pages\Statements::getNavigationLabel())->toBe('Statements');

    App::setLocale('bn');

    expect(\App\Filament\Admin\Resources\Sales\SaleResource::getPluralModelLabel())->toBe('বিক্রয়সমূহ')
        ->and(\App\Filament\Admin\Resources\Sales\SaleResource::getNavigationGroup())->toBe('বিক্রয়')
        ->and(\App\Filament\Admin\Resources\Payrolls\PayrollResource::getModelLabel())->toBe('বেতন')
        ->and(\App\Filament\Admin\Pages\Statements::getNavigationLabel())->toBe('হিসাব বিবরণী');
});

test('the panel itself renders in bengali', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');
    Setting::query()->create(['key' => Localization::SettingKey, 'value' => 'bn']);

    $this->actingAs($user)->get('/sales')
        ->assertOk()
        ->assertSee('বিক্রয়সমূহ')
        // Filament ships its own Bengali strings, so its built-in UI follows the same locale.
        ->assertSee(__('filament-tables::table.fields.search.placeholder'));
});
