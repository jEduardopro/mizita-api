<?php

declare(strict_types=1);

use App\Domains\Staff\ValueObjects\StaffRole;

/*
| A backed enum, so what is worth asserting is the strings behind it. They are
| not cosmetic: the staff_members role check constraint, the seeded Spatie role
| names and the partial unique index that caps an account at one owned business
| are all written in terms of these two literals. Changing one here without a
| migration would fail at the database, not here - which is why the values are
| pinned rather than read back from the enum.
*/

it('spells the two roles exactly as the schema and the seeder do', function () {
    expect(StaffRole::Owner->value)->toBe('owner')
        ->and(StaffRole::Member->value)->toBe('staff');
});

it('calls the ordinary membership Member in code and staff in the database', function () {
    // The case name and the stored value differ on purpose: "Member" reads
    // correctly beside Owner, while "staff" is the word the product uses.
    expect(StaffRole::from('staff'))->toBe(StaffRole::Member)
        ->and(StaffRole::from('owner'))->toBe(StaffRole::Owner);
});

it('has exactly two roles, so a new one cannot be added without a migration', function () {
    expect(StaffRole::cases())->toBe([StaffRole::Owner, StaffRole::Member]);
});

it('names only roles the authorization catalogue defines', function (StaffRole $role) {
    // The domain's vocabulary and the catalogue are two files that have to agree
    // on the same words: this enum is what the code says, and the catalogue is
    // what gets written to the roles table. A role named here and absent there is
    // an assignment that fails with RoleDoesNotExist at signup.
    $catalogue = require dirname(__DIR__, 5).'/config/authorization.php';

    expect(array_keys($catalogue['roles']))->toContain($role->value);
})->with(StaffRole::cases());

it('refuses a role the database would reject', function (string $value) {
    expect(StaffRole::tryFrom($value))->toBeNull();
})->with([
    'unknown' => 'admin',
    'the case name rather than the value' => 'Member',
    'wrong case' => 'Owner',
    'empty' => '',
    'padded' => ' owner ',
]);
