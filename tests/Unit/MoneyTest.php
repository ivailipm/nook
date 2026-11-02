<?php

use App\Support\Money;

it('prints euros with two decimals, a dot and no thousands separator', function (float $amount, string $printed) {
    expect(Money::format($amount))->toBe($printed);
})->with([
    'a price' => [3.49, '€3.49'],
    'nothing' => [0.0, '€0.00'],
    'over a thousand' => [1234.5, '€1234.50'],
    'a float sum' => [47.959999999999994, '€47.96'],
    'a twelfth of a yearly price' => [4.165833, '€4.17'],
]);
