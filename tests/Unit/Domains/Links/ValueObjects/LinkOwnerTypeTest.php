<?php

declare(strict_types=1);

use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;

/**
 * @param  list<AddressOwnerType|LinkOwnerType|PhoneOwnerType>  $cases
 * @return array<string, string>
 */
function aliasesByOwner(array $cases): array
{
    return array_column($cases, 'value', 'name');
}

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

it('spells an owner it shares with another polymorphic domain exactly as that domain spells it', function (array $theirs, string $domain) {
    $ours = aliasesByOwner(LinkOwnerType::cases());

    foreach (array_intersect_key($theirs, $ours) as $owner => $alias) {
        expect($ours[$owner])->toBe($alias, "Links and {$domain} disagree on the alias for [{$owner}]");
    }
})->with([
    'addresses' => [fn () => aliasesByOwner(AddressOwnerType::cases()), 'Addresses'],
    'phones' => [fn () => aliasesByOwner(PhoneOwnerType::cases()), 'Phones'],
]);

it('never gives an alias another polymorphic domain already gave to a different owner', function (array $theirs, string $domain) {
    foreach (aliasesByOwner(LinkOwnerType::cases()) as $owner => $alias) {
        $clash = array_search($alias, $theirs, true);

        expect($clash === false || $clash === $owner)
            ->toBeTrue("[{$alias}] names [{$owner}] here and [{$clash}] in {$domain}");
    }
})->with([
    'addresses' => [fn () => aliasesByOwner(AddressOwnerType::cases()), 'Addresses'],
    'phones' => [fn () => aliasesByOwner(PhoneOwnerType::cases()), 'Phones'],
]);

it('is free to name fewer owners than they do, because a customer keeps no links', function () {
    expect(aliasesByOwner(AddressOwnerType::cases()))->toHaveKey('Customer')
        ->and(aliasesByOwner(LinkOwnerType::cases()))->not->toHaveKey('Customer');
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
