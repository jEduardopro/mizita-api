<?php

declare(strict_types=1);

use App\Domains\Addresses\ValueObjects\AddressOwnerType;

it('backs each owner with the morph alias the database stores', function (AddressOwnerType $ownerType, string $alias) {
    expect($ownerType->value)->toBe($alias);
})->with([
    'business' => [AddressOwnerType::Business, 'business'],
    'staff member' => [AddressOwnerType::StaffMember, 'staff_member'],
]);

it('exposes exactly the owners an address can belong to', function () {
    expect(array_column(AddressOwnerType::cases(), 'value'))->toBe(['business', 'staff_member']);
});

it('resolves a stored alias back to its case', function (string $alias, AddressOwnerType $ownerType) {
    expect(AddressOwnerType::from($alias))->toBe($ownerType);
})->with([
    'business' => ['business', AddressOwnerType::Business],
    'staff member' => ['staff_member', AddressOwnerType::StaffMember],
]);

it('refuses an alias no owning domain answers to', function (string $alias) {
    expect(AddressOwnerType::tryFrom($alias))->toBeNull();
})->with([
    'a domain that owns no address' => 'appointment',
    'a synonym from the ubiquitous language' => 'account',
    'wrong case' => 'Business',
    'camel case' => 'staffMember',
    'a model class' => 'App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel',
    'padded' => ' business ',
    'empty' => '',
]);

it('fails loudly rather than guessing when a row carries an alias it does not know', function () {
    expect(fn () => AddressOwnerType::from('customer'))->toThrow(ValueError::class);
});
