<?php

declare(strict_types=1);

use App\Domains\Staff\ValueObjects\StaffRole;

it('spells the two roles exactly as the schema and the seeder do', function () {
    expect(StaffRole::Owner->value)->toBe('owner')
        ->and(StaffRole::Member->value)->toBe('staff');
});

it('calls the ordinary membership Member in code and staff in the database', function () {
    expect(StaffRole::from('staff'))->toBe(StaffRole::Member)
        ->and(StaffRole::from('owner'))->toBe(StaffRole::Owner);
});

it('has exactly two roles, so a new one cannot be added without a migration', function () {
    expect(StaffRole::cases())->toBe([StaffRole::Owner, StaffRole::Member]);
});

it('names only roles the authorization catalogue defines', function (StaffRole $role) {
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
