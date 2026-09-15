<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\StaffMemberSummary;
use App\Domains\Staff\Infrastructure\Http\Resources\StaffMemberResource;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\Staff\StaffFixtures;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function serializedStaffMember(StaffMemberSummary $member): array
{
    return (array) StaffMemberResource::make($member)->response()->getData(true)['data'];
}

it('serializes exactly the four fields the selector reads', function () {
    expect(serializedStaffMember(new StaffMemberSummary(
        id: StaffFixtures::MEMBER_ID,
        name: 'Ada Lovelace',
        email: 'ada@example.com',
        role: StaffRole::Owner,
    )))->toBe([
        'id' => StaffFixtures::MEMBER_ID,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'role' => 'owner',
    ]);
});

it('sends the role as the value the seeded role carries', function () {
    expect(serializedStaffMember(new StaffMemberSummary(
        id: StaffFixtures::SECOND_MEMBER_ID,
        name: 'Grace Hopper',
        email: 'grace@example.com',
        role: StaffRole::Member,
    ))['role'])->toBe('staff');
});

it('never puts the business or the account behind a membership on the wire', function () {
    $member = serializedStaffMember(new StaffMemberSummary(
        id: StaffFixtures::MEMBER_ID,
        name: 'Ada Lovelace',
        email: 'ada@example.com',
        role: StaffRole::Owner,
    ));

    expect($member)->not->toHaveKey('business_id')
        ->and($member)->not->toHaveKey('account_id');
});
