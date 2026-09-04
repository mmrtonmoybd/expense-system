<?php

declare(strict_types=1);

use App\Support\Money;

test('scaling rounds half away from zero at the target precision', function (?string $value, string $expected) {
    expect(Money::bdt($value))->toBe($expected);
})->with([
    ['1.005', '1.01'],
    ['2.675', '2.68'],
    ['1.004999', '1.00'],
    ['-1.005', '-1.01'],
    ['-2.675', '-2.68'],
    ['0', '0.00'],
    [null, '0.00'],
    ['', '0.00'],
]);

test('an amount is the quantity times the rate rounded to two decimals', function () {
    expect(Money::amount('3', '100.125'))->toBe('300.38')
        ->and(Money::amount('0.1', '0.2'))->toBe('0.02')
        ->and(Money::amount('1000', '118.7'))->toBe('118700.00')
        ->and(Money::amount('0', '118.7'))->toBe('0.00');
});

test('a rate spreads an amount over a quantity and never divides by zero', function () {
    expect(Money::rateFor('100', '3'))->toBe('33.33333333')
        ->and(Money::rateFor('11870', '100'))->toBe('118.70000000')
        ->and(Money::rateFor('100', '0'))->toBe('0.00000000');
});

test('additions and subtractions stay exact at their own scale', function () {
    expect(Money::addBdt('0.1', '0.2'))->toBe('0.30')
        ->and(Money::subBdt('0.3', '0.1'))->toBe('0.20')
        ->and(Money::addUsd('0.00000001', '0.00000002'))->toBe('0.00000003')
        ->and(Money::subUsd('1', '0.00000001'))->toBe('0.99999999');
});

test('comparisons and helpers work at the intended precision', function () {
    expect(Money::compareBdt('1.001', '1.002'))->toBe(0)
        ->and(Money::compareUsd('1.000000001', '1.000000002'))->toBe(0)
        ->and(Money::compareUsd('1.00000002', '1.00000001'))->toBe(1)
        ->and(Money::isPositiveUsd('0.000000001'))->toBeFalse()
        ->and(Money::isPositiveUsd('0.00000001'))->toBeTrue()
        ->and(Money::minUsd('5', '3'))->toBe('3.00000000')
        ->and(Money::minUsd('3', '5'))->toBe('3.00000000');
});

test('dividing a bdt amount rounds and refuses a zero divisor', function () {
    expect(Money::divideBdt('10', '3'))->toBe('3.33')
        ->and(Money::divideBdt('10', '4'))->toBe('2.50')
        ->and(fn () => Money::divideBdt('10', '0'))->toThrow(DivisionByZeroError::class);
});
