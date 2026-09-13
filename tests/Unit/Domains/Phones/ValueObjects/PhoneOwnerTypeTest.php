<?php

declare(strict_types=1);

use App\Domains\Phones\ValueObjects\PhoneOwnerType;

/*
| These backing values are what phones.phoneable_type holds, and the alias every
| owning domain registers with Relation::enforceMorphMap. Changing one orphans
| every row already written with the old alias and silently breaks the morph map
| of a domain this one does not even import - which is exactly why they are
| pinned here rather than trusted to stay put.
*/

it('backs each owner with the morph alias the database stores', function (PhoneOwnerType $ownerType, string $alias) {
    expect($ownerType->value)->toBe($alias);
})->with([
    'business' => [PhoneOwnerType::Business, 'business'],
    'staff member' => [PhoneOwnerType::StaffMember, 'staff_member'],
    'customer' => [PhoneOwnerType::Customer, 'customer'],
]);

it('exposes exactly the owners a phone can belong to', function () {
    expect(array_column(PhoneOwnerType::cases(), 'value'))
        ->toBe(['business', 'staff_member', 'customer']);
});

it('resolves a stored alias back to its case', function (string $alias, PhoneOwnerType $ownerType) {
    expect(PhoneOwnerType::from($alias))->toBe($ownerType);
})->with([
    'business' => ['business', PhoneOwnerType::Business],
    'staff member' => ['staff_member', PhoneOwnerType::StaffMember],
    'customer' => ['customer', PhoneOwnerType::Customer],
]);

it('refuses an alias no owning domain answers to', function (string $alias) {
    expect(PhoneOwnerType::tryFrom($alias))->toBeNull();
})->with([
    'unknown owner' => 'appointment',
    'wrong case' => 'Business',
    'camel case' => 'staffMember',
    'a model class' => 'App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel',
    'padded' => ' business ',
    'empty' => '',
]);
