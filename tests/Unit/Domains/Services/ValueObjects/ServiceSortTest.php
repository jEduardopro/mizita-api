<?php

declare(strict_types=1);

use App\Domains\Services\ValueObjects\ServiceSort;

it('is the whitelist of what a caller may sort by', function () {
    expect(array_map(
        static fn (ServiceSort $sort): string => $sort->value,
        ServiceSort::cases(),
    ))->toBe(['name', 'price', 'duration', 'created_at']);
});

it('refuses anything the enum does not name, so no caller string reaches a column', function (string $value) {
    expect(ServiceSort::tryFrom($value))->toBeNull();
})->with([
    'a real column it does not expose' => 'id',
    'the tenant column' => 'business_id',
    'the storage column behind duration' => 'duration_minutes',
    'sql' => 'name; drop table services',
    'uppercase' => 'NAME',
    'empty' => '',
]);
