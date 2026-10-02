<?php

declare(strict_types=1);

use App\Domains\Platform\ValueObjects\PlatformBusinessSort;

it('sorts by exactly the four columns the business list offers', function () {
    expect(array_map(fn (PlatformBusinessSort $sort): string => $sort->value, PlatformBusinessSort::cases()))
        ->toBe(['created_at', 'name', 'services_count', 'customers_count']);
});

it('recognises no column outside that list', function (string $column) {
    expect(PlatformBusinessSort::tryFrom($column))->toBeNull();
})->with(['plan', 'owner', 'slug', 'id', 'createdAt', 'NAME', '']);
