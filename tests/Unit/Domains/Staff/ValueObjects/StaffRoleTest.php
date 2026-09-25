<?php

declare(strict_types=1);

use App\Domains\Staff\Exceptions\InvalidTeamLevel;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Contracts\DomainFailure;

it('spells the three roles exactly as the schema and the seeder do', function () {
    expect(StaffRole::Owner->value)->toBe('owner')
        ->and(StaffRole::Member->value)->toBe('staff')
        ->and(StaffRole::NoAccess->value)->toBe('no_access');
});

it('calls the ordinary membership Member in code and staff in the database', function () {
    expect(StaffRole::from('staff'))->toBe(StaffRole::Member)
        ->and(StaffRole::from('owner'))->toBe(StaffRole::Owner)
        ->and(StaffRole::from('no_access'))->toBe(StaffRole::NoAccess);
});

it('has exactly three roles, so a new one cannot be added without a migration', function () {
    expect(StaffRole::cases())->toBe([StaffRole::Owner, StaffRole::Member, StaffRole::NoAccess]);
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
    'the no access case name' => 'NoAccess',
    'hyphenated' => 'no-access',
    'empty' => '',
    'padded' => ' owner ',
]);

describe('which roles grant access', function () {
    it('lets an owner and a staff member in', function (StaffRole $role) {
        expect($role->grantsAccess())->toBeTrue();
    })->with([
        'owner' => StaffRole::Owner,
        'staff' => StaffRole::Member,
    ]);

    it('keeps a no access member out', function () {
        expect(StaffRole::NoAccess->grantsAccess())->toBeFalse();
    });
});

describe('which roles an owner may hand out', function () {
    it('never hands out the owner level', function () {
        expect(StaffRole::Owner->isAssignable())->toBeFalse();
    });

    it('hands out staff and no access', function (StaffRole $role) {
        expect($role->isAssignable())->toBeTrue();
    })->with([
        'staff' => StaffRole::Member,
        'no access' => StaffRole::NoAccess,
    ]);

    it('lists the assignable levels in declaration order, as the values the wire carries', function () {
        expect(StaffRole::assignableValues())->toBe(['staff', 'no_access']);
    });

    it('resolves an assignable level from its wire value', function (string $level, StaffRole $role) {
        expect(StaffRole::assignableFrom($level))->toBe($role);
    })->with([
        'staff' => ['staff', StaffRole::Member],
        'no access' => ['no_access', StaffRole::NoAccess],
    ]);

    it('refuses the owner level with a domain failure', function () {
        expect(fn () => StaffRole::assignableFrom('owner'))
            ->toThrow(InvalidTeamLevel::class, 'The owner level belongs to whoever registered the business and cannot be given.');
    });

    it('refuses a level it does not know with a domain failure', function (string $level) {
        $thrown = null;

        try {
            StaffRole::assignableFrom($level);
        } catch (InvalidTeamLevel $refusal) {
            $thrown = $refusal;
        }

        expect($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown?->errorCode())->toBe('invalid_team_level')
            ->and($thrown?->getMessage())->toBe("[{$level}] is not a permission level a team member can be given.");
    })->with([
        'unknown' => 'admin',
        'empty' => '',
        'the case name' => 'Member',
        'uppercase' => 'STAFF',
        'padded' => ' staff ',
    ]);
});
