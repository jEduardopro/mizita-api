<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Infrastructure\Http\Resources\TeamMemberResource;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\PhoneNumbers;
use Tests\Support\Staff\StaffFixtures;
use Tests\TestCase;

uses(TestCase::class);

function teamMemberData(
    StaffRole $level = StaffRole::Member,
    bool $withPhone = true,
    bool $invitationPending = true,
    bool $temporaryPasswordAvailable = true,
): TeamMemberData {
    return new TeamMemberData(
        id: StaffFixtures::SECOND_MEMBER_ID,
        name: 'Grace Hopper',
        email: 'grace@example.com',
        phone: $withPhone ? PhoneNumbers::mexican() : null,
        photoUrl: $withPhone ? StaffFixtures::PHOTO_URL : null,
        jobTitle: $withPhone ? StaffFixtures::JOB_TITLE : null,
        about: $withPhone ? StaffFixtures::ABOUT : null,
        level: $level,
        invitationPending: $invitationPending,
        temporaryPasswordAvailable: $temporaryPasswordAvailable,
        createdAt: new DateTimeImmutable('2026-03-29T01:30:00+00:00'),
    );
}

/**
 * @return array<string, mixed>
 */
function serializedTeamMember(TeamMemberData $member): array
{
    return (array) TeamMemberResource::make($member)->response()->getData(true)['data'];
}

it('serializes exactly the fields the team screen reads, wrapped in data', function () {
    expect(serializedTeamMember(teamMemberData()))->toBe([
        'id' => StaffFixtures::SECOND_MEMBER_ID,
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'phone' => [
            'country_code' => 'MX',
            'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER,
            'e164' => PhoneNumbers::MX_E164,
        ],
        'photo_url' => StaffFixtures::PHOTO_URL,
        'job_title' => StaffFixtures::JOB_TITLE,
        'about' => StaffFixtures::ABOUT,
        'level' => 'staff',
        'invitation_pending' => true,
        'temporary_password_available' => true,
        'created_at' => '2026-03-29T01:30:00+00:00',
    ]);
});

it('identifies the member by its uuid, never by a sequential key', function () {
    $id = serializedTeamMember(teamMemberData())['id'];

    expect($id)->toBeString()
        ->and($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and(is_numeric($id))->toBeFalse();
});

it('never puts the business, the account or the profile behind a member on the wire', function () {
    $member = serializedTeamMember(teamMemberData());

    expect($member)->not->toHaveKeys(['business_id', 'account_id', 'profile_id', 'staff_member_id', 'temporary_password'])
        ->and(json_encode($member))->not->toContain(StaffFixtures::SECOND_ACCOUNT_ID);
});

it('sends every level as the value the seeded role carries', function (StaffRole $level, string $value) {
    expect(serializedTeamMember(teamMemberData(level: $level))['level'])->toBe($value);
})->with([
    'owner' => [StaffRole::Owner, 'owner'],
    'staff' => [StaffRole::Member, 'staff'],
    'no access' => [StaffRole::NoAccess, 'no_access'],
]);

it('sends null for every optional detail the member lacks', function () {
    $member = serializedTeamMember(teamMemberData(withPhone: false, invitationPending: false, temporaryPasswordAvailable: false));

    expect($member['phone'])->toBeNull()
        ->and($member['photo_url'])->toBeNull()
        ->and($member['job_title'])->toBeNull()
        ->and($member['about'])->toBeNull()
        ->and($member['invitation_pending'])->toBeFalse()
        ->and($member['temporary_password_available'])->toBeFalse();
});
