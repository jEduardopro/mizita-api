<?php

declare(strict_types=1);

use App\Domains\Phones\ValueObjects\PhoneOwnerType;

it('backs each owner with the morph alias the database stores', function (PhoneOwnerType $ownerType, string $alias) {
    expect($ownerType->value)->toBe($alias);
})->with([
    'business' => [PhoneOwnerType::Business, 'business'],
    'staff member' => [PhoneOwnerType::StaffMember, 'staff_member'],
    'customer' => [PhoneOwnerType::Customer, 'customer'],
    'staff profile' => [PhoneOwnerType::StaffProfile, 'staff_profile'],
]);

it('exposes exactly the owners a phone can belong to', function () {
    expect(array_column(PhoneOwnerType::cases(), 'value'))
        ->toBe(['business', 'staff_member', 'customer', 'staff_profile']);
});

it('resolves a stored alias back to its case', function (string $alias, PhoneOwnerType $ownerType) {
    expect(PhoneOwnerType::from($alias))->toBe($ownerType);
})->with([
    'business' => ['business', PhoneOwnerType::Business],
    'staff member' => ['staff_member', PhoneOwnerType::StaffMember],
    'customer' => ['customer', PhoneOwnerType::Customer],
    'staff profile' => ['staff_profile', PhoneOwnerType::StaffProfile],
]);

it('refuses an alias no owning domain answers to', function (string $alias) {
    expect(PhoneOwnerType::tryFrom($alias))->toBeNull();
})->with([
    'unknown owner' => 'appointment',
    'a synonym from the ubiquitous language' => 'account',
    'a synonym for an owner it does have' => 'client',
    'wrong case' => 'Business',
    'camel case' => 'staffMember',
    'a model class' => 'App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel',
    'padded' => ' business ',
    'empty' => '',
]);

it('fails loudly rather than guessing when a row carries an alias it does not know', function () {
    expect(fn () => PhoneOwnerType::from('appointment'))->toThrow(ValueError::class);
});
