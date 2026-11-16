<?php

use App\Billing\Usd;

test('a decimal dollar string becomes exact nanodollars', function (string $decimal, int $nanos) {
    expect(Usd::toNanos($decimal))->toBe($nanos);
})->with([
    'nine decimals' => ['0.046125000', 46_125_000],
    'a rate' => ['12.50', 12_500_000_000],
    'whole dollars' => ['5', 5_000_000_000],
    'one nanodollar' => ['0.000000001', 1],
]);

test('an amount that is not an exact decimal is rejected', function (string $decimal) {
    Usd::toNanos($decimal);
})->with([
    'more than nine decimals' => ['0.0000000001'],
    'scientific notation' => ['1.0E-6'],
    'negative' => ['-1.00'],
    'empty' => [''],
])->throws(InvalidArgumentException::class);

test('nanodollars are formatted exactly with at least two decimals', function (int $nanos, string $formatted) {
    expect(Usd::format($nanos))->toBe($formatted);
})->with([
    'fractions of a cent' => [381_887_500, '$0.3818875'],
    'whole dollars' => [5_000_000_000, '$5.00'],
    'ten cents' => [100_000_000, '$0.10'],
    'one nanodollar' => [1, '$0.000000001'],
    'zero' => [0, '$0.00'],
    'negative' => [-46_125_000, '-$0.046125'],
]);
