<?php

use App\Services\LegacyBottleCapacityResolver;

test('legacy perfume capacity uses the size stated in the product name', function (string $name, int $expected) {
    expect(app(LegacyBottleCapacityResolver::class)->resolve($name))
        ->toMatchArray(['volume_ml' => $expected, 'source' => 'name', 'error' => null]);
})->with([
    ['Al Haramain Aqua Dubai 75ML', 75],
    ['Gold edition 120 ML', 120],
    ['Valentino Intense 50ml', 50],
    ['Oud 5 ml', 5],
]);

test('legacy perfume capacity defaults only when the name states no capacity', function () {
    expect(app(LegacyBottleCapacityResolver::class)->resolve('Rasasi Hawas Ice'))
        ->toMatchArray(['volume_ml' => 100, 'source' => 'default', 'error' => null]);
});

test('legacy perfume capacity blocks ambiguous or non-ml sizes rather than guessing', function (string $name) {
    expect(app(LegacyBottleCapacityResolver::class)->resolve($name)['error'])->not->toBeNull();
})->with([
    'Perfume 50ml y 100ml',
    'Perfume 3.4 oz',
    'Perfume 1 L',
]);
