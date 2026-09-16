<?php

declare(strict_types=1);

use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Links\ValueObjects\LinkOwnerType;

it('backs each owner with the morph alias the database stores', function (LinkOwnerType $ownerType, string $alias) {
    expect($ownerType->value)->toBe($alias);
})->with([
    'business' => [LinkOwnerType::Business, 'business'],
    'staff member' => [LinkOwnerType::StaffMember, 'staff_member'],
]);

it('exposes exactly the owners a link can belong to', function () {
    expect(array_column(LinkOwnerType::cases(), 'value'))->toBe(['business', 'staff_member']);
});

it('resolves a stored alias back to its case', function (string $alias, LinkOwnerType $ownerType) {
    expect(LinkOwnerType::from($alias))->toBe($ownerType);
})->with([
    'business' => ['business', LinkOwnerType::Business],
    'staff member' => ['staff_member', LinkOwnerType::StaffMember],
]);

it('speaks the same aliases as the other polymorphic owners, so one row cannot mean two things', function () {
    expect(array_column(LinkOwnerType::cases(), 'value'))
        ->toBe(array_column(AddressOwnerType::cases(), 'value'));
});

it('refuses an alias no owning domain answers to', function (string $alias) {
    expect(LinkOwnerType::tryFrom($alias))->toBeNull();
})->with([
    'a domain that owns no link' => 'appointment',
    'a synonym from the ubiquitous language' => 'account',
    'wrong case' => 'Business',
    'camel case' => 'staffMember',
    'a model class' => 'App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel',
    'padded' => ' business ',
    'empty' => '',
]);

it('fails loudly rather than guessing when a row carries an alias it does not know', function () {
    expect(fn () => LinkOwnerType::from('customer'))->toThrow(ValueError::class);
});
