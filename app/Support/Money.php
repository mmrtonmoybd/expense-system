<?php

declare(strict_types=1);

namespace App\Support;

use DivisionByZeroError;

/**
 * Centralised fixed-precision financial arithmetic.
 *
 * Every amount is handled as a decimal string through bcmath; floats are never used.
 * Rounding is half-up at the target scale: 8 decimals for USD quantities and exchange
 * rates, 2 decimals for BDT amounts.
 */
final class Money
{
    public const int UsdScale = 8;

    public const int RateScale = 8;

    public const int BdtScale = 2;

    private const int GuardScale = 16;

    public static function usd(string|int|null $value): string
    {
        return self::scale($value, self::UsdScale);
    }

    public static function rate(string|int|null $value): string
    {
        return self::scale($value, self::RateScale);
    }

    public static function bdt(string|int|null $value): string
    {
        return self::scale($value, self::BdtScale);
    }

    /** Quantity multiplied by a rate, rounded to BDT precision. */
    public static function amount(string|int|null $quantity, string|int|null $rate): string
    {
        return self::scale(bcmul(self::normalize($quantity), self::normalize($rate), self::GuardScale), self::BdtScale);
    }

    /** Effective rate for an amount spread over a USD quantity. */
    public static function rateFor(string|int|null $amount, string|int|null $quantity): string
    {
        $quantity = self::normalize($quantity);

        if (self::isZero($quantity, self::UsdScale)) {
            return self::rate('0');
        }

        return self::scale(bcdiv(self::normalize($amount), $quantity, self::GuardScale), self::RateScale);
    }

    public static function addUsd(string|int|null $left, string|int|null $right): string
    {
        return self::scale(bcadd(self::normalize($left), self::normalize($right), self::GuardScale), self::UsdScale);
    }

    public static function subUsd(string|int|null $left, string|int|null $right): string
    {
        return self::scale(bcsub(self::normalize($left), self::normalize($right), self::GuardScale), self::UsdScale);
    }

    public static function addBdt(string|int|null $left, string|int|null $right): string
    {
        return self::scale(bcadd(self::normalize($left), self::normalize($right), self::GuardScale), self::BdtScale);
    }

    public static function subBdt(string|int|null $left, string|int|null $right): string
    {
        return self::scale(bcsub(self::normalize($left), self::normalize($right), self::GuardScale), self::BdtScale);
    }

    public static function compareUsd(string|int|null $left, string|int|null $right): int
    {
        return bccomp(self::normalize($left), self::normalize($right), self::UsdScale);
    }

    public static function compareBdt(string|int|null $left, string|int|null $right): int
    {
        return bccomp(self::normalize($left), self::normalize($right), self::BdtScale);
    }

    public static function isPositiveUsd(string|int|null $value): bool
    {
        return self::compareUsd($value, '0') === 1;
    }

    public static function isZero(string|int|null $value, int $scale = self::UsdScale): bool
    {
        return bccomp(self::normalize($value), '0', $scale) === 0;
    }

    /** Minimum of two USD quantities. */
    public static function minUsd(string|int|null $left, string|int|null $right): string
    {
        return self::compareUsd($left, $right) === 1 ? self::usd($right) : self::usd($left);
    }

    public static function divideBdt(string|int|null $amount, string|int|null $divisor): string
    {
        $divisor = self::normalize($divisor);

        if (self::isZero($divisor, self::GuardScale)) {
            throw new DivisionByZeroError('Cannot divide a BDT amount by zero.');
        }

        return self::scale(bcdiv(self::normalize($amount), $divisor, self::GuardScale), self::BdtScale);
    }

    private static function scale(string|int|null $value, int $scale): string
    {
        $value = self::normalize($value);
        $half = $scale === 0 ? '0.5' : '0.'.str_repeat('0', $scale).'5';

        return bccomp($value, '0', self::GuardScale) === -1
            ? bcsub($value, $half, $scale)
            : bcadd($value, $half, $scale);
    }

    private static function normalize(string|int|null $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return (string) $value;
    }
}
