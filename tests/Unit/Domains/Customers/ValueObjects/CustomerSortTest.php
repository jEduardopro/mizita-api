<?php

declare(strict_types=1);

use App\Domains\Customers\ValueObjects\CustomerSort;

it('is the whitelist of what a caller may sort by', function () {
    expect(array_column(CustomerSort::cases(), 'value'))->toBe(['name', 'created_at']);
});

it('resolves a value the caller sent back to its case', function (string $value, CustomerSort $sort) {
    expect(CustomerSort::from($value))->toBe($sort);
})->with([
    'name' => ['name', CustomerSort::Name],
    'created at' => ['created_at', CustomerSort::CreatedAt],
]);

it('refuses anything the enum does not name, so no caller string reaches a column', function (string $value) {
    expect(CustomerSort::tryFrom($value))->toBeNull();
})->with([
    'a real column it does not expose' => 'id',
    'the tenant column' => 'business_id',
    'a column holding a customer fact' => 'email',
    'the soft delete column' => 'deleted_at',
    'camel case' => 'createdAt',
    'uppercase' => 'NAME',
    'sql' => 'name; drop table customers',
    'padded' => ' name ',
    'empty' => '',
]);
