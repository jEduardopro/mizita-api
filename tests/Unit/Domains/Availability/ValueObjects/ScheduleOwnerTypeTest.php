<?php

declare(strict_types=1);

use App\Domains\Availability\ValueObjects\ScheduleOwnerType;

it('stores each owner as the morph alias its model registers', function (ScheduleOwnerType $ownerType, string $alias) {
    expect($ownerType->value)->toBe($alias);
})->with([
    'a business' => [ScheduleOwnerType::Business, 'business'],
    'a staff member' => [ScheduleOwnerType::StaffMember, 'staff_member'],
]);

it('knows exactly two kinds of owner', function () {
    expect(ScheduleOwnerType::cases())->toHaveCount(2)
        ->and(array_column(ScheduleOwnerType::cases(), 'value'))->toBe(['business', 'staff_member']);
});

it('never spells an owner with a word from the ubiquitous language', function () {
    $aliases = array_column(ScheduleOwnerType::cases(), 'value');

    expect($aliases)->not->toContain('account')
        ->and($aliases)->not->toContain('staff')
        ->and($aliases)->not->toContain('staffMember');
});

it('reads an owner back from the alias a row holds', function (string $alias, ScheduleOwnerType $ownerType) {
    expect(ScheduleOwnerType::from($alias))->toBe($ownerType);
})->with([
    'business' => ['business', ScheduleOwnerType::Business],
    'staff member' => ['staff_member', ScheduleOwnerType::StaffMember],
]);
