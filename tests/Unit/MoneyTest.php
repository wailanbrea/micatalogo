<?php

use App\Support\Money;

test('converts various valid monetary formats to cents accurately without float artifacts', function () {
    expect(Money::toCents(0))->toBe(0)
        ->and(Money::toCents('0'))->toBe(0)
        ->and(Money::toCents('0.01'))->toBe(1)
        ->and(Money::toCents('1'))->toBe(100)
        ->and(Money::toCents('1.10'))->toBe(110)
        ->and(Money::toCents('1250.75'))->toBe(125075)
        ->and(Money::toCents('2500,50'))->toBe(250050)
        ->and(Money::toCents('2,500.50'))->toBe(250050)
        ->and(Money::toCents('2.500,50'))->toBe(250050)
        ->and(Money::toCents('RD$ 2,500.50'))->toBe(250050)
        ->and(Money::toCents('$1250.75'))->toBe(125075)
        ->and(Money::toCents('-50.25'))->toBe(-5025);
});

test('converts cents to decimal string deterministically', function () {
    expect(Money::toDecimal(0))->toBe('0.00')
        ->and(Money::toDecimal(1))->toBe('0.01')
        ->and(Money::toDecimal(110))->toBe('1.10')
        ->and(Money::toDecimal(125075))->toBe('1250.75')
        ->and(Money::toDecimal(-5025))->toBe('-50.25');
});

test('rejects invalid or ambiguous monetary strings', function ($invalid) {
    expect(fn () => Money::toCents($invalid))->toThrow(InvalidArgumentException::class);
})->with([
    'abc',
    'RD$abc',
    '1,2,3',
    '--250',
    '12.34.56',
    '12,34,56',
]);
