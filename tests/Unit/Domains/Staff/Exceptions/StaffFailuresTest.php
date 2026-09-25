<?php

declare(strict_types=1);

use App\Domains\Accounts\Exceptions\InvalidAccountName;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;
use App\Domains\Staff\Exceptions\DuplicateTeamInvitationEmail;
use App\Domains\Staff\Exceptions\InvalidProfileAbout;
use App\Domains\Staff\Exceptions\InvalidProfileJobTitle;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidProfilePhone;
use App\Domains\Staff\Exceptions\InvalidTeamInvitation;
use App\Domains\Staff\Exceptions\InvalidTeamLevel;
use App\Domains\Staff\Exceptions\InvalidTeamMemberEmail;
use App\Domains\Staff\Exceptions\InvalidTeamSearch;
use App\Domains\Staff\Exceptions\OwnerCannotBeRemoved;
use App\Domains\Staff\Exceptions\OwnerLevelIsFixed;
use App\Domains\Staff\Exceptions\ProfilePhotoTooLarge;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\StaffProfileNotFound;
use App\Domains\Staff\Exceptions\TeamInvitationNotPending;
use App\Domains\Staff\Exceptions\TeamMemberAlreadyExists;
use App\Domains\Staff\Exceptions\TeamMemberHasUpcomingAppointments;
use App\Domains\Staff\Exceptions\UnsupportedProfilePhoto;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Staff\StaffFixtures;

/**
 * @return array<string, array{DomainFailure, string, DomainFailureKind}>
 */
function staffFailures(): array
{
    return [
        'an account that already owns a business' => [
            AccountAlreadyOwnsBusiness::forAccount(StaffFixtures::ACCOUNT_ID),
            'owner_already_has_business',
            DomainFailureKind::Conflict,
        ],
        'a member nobody has' => [
            StaffMemberNotFound::withId(StaffFixtures::MEMBER_ID),
            'staff_member_not_found',
            DomainFailureKind::NotFound,
        ],
        'an account with no membership here' => [
            StaffMemberNotFound::forAccount(StaffFixtures::ACCOUNT_ID),
            'staff_member_not_found',
            DomainFailureKind::NotFound,
        ],
        'a profile nobody has' => [
            StaffProfileNotFound::withId(StaffFixtures::PROFILE_ID),
            'staff_profile_not_found',
            DomainFailureKind::NotFound,
        ],
        'a member with no profile' => [
            StaffProfileNotFound::forStaffMember(StaffFixtures::MEMBER_ID),
            'staff_profile_not_found',
            DomainFailureKind::NotFound,
        ],
        'an empty name' => [
            InvalidProfileName::empty(),
            'invalid_profile_name',
            DomainFailureKind::Invalid,
        ],
        'a name past the limit' => [
            InvalidProfileName::tooLong(255),
            'invalid_profile_name',
            DomainFailureKind::Invalid,
        ],
        'a name the account refused' => [
            InvalidProfileName::rejectedByAccount(InvalidAccountName::empty()),
            'invalid_profile_name',
            DomainFailureKind::Invalid,
        ],
        'a job title past the limit' => [
            InvalidProfileJobTitle::tooLong(120),
            'invalid_profile_job_title',
            DomainFailureKind::Invalid,
        ],
        'a description past the limit' => [
            InvalidProfileAbout::tooLong(1000),
            'invalid_profile_about',
            DomainFailureKind::Invalid,
        ],
        'an undialable country' => [
            InvalidProfilePhone::inCountry('XX'),
            'invalid_profile_phone',
            DomainFailureKind::Invalid,
        ],
        'a malformed number' => [
            InvalidProfilePhone::malformed('MX'),
            'invalid_profile_phone',
            DomainFailureKind::Invalid,
        ],
        'a number the parser could not dial' => [
            InvalidProfilePhone::forCountry(CountryCode::Mx),
            'invalid_profile_phone',
            DomainFailureKind::Invalid,
        ],
        'a photo past the size limit' => [
            ProfilePhotoTooLarge::atBytes(3_000_000, 2_097_152),
            'profile_photo_too_large',
            DomainFailureKind::Invalid,
        ],
        'an upload with no file' => [
            UnsupportedProfilePhoto::missing(),
            'unsupported_profile_photo',
            DomainFailureKind::Invalid,
        ],
        'a photo type the profile cannot show' => [
            UnsupportedProfilePhoto::ofType('image/gif'),
            'unsupported_profile_photo',
            DomainFailureKind::Invalid,
        ],
        'an invitation with nobody in it' => [
            InvalidTeamInvitation::empty(),
            'invalid_team_invitation',
            DomainFailureKind::Invalid,
        ],
        'an invitation past the batch limit' => [
            InvalidTeamInvitation::tooManyMembers(20),
            'invalid_team_invitation',
            DomainFailureKind::Invalid,
        ],
        'the same email twice in a batch' => [
            DuplicateTeamInvitationEmail::for('grace@example.com'),
            'duplicate_team_invitation_email',
            DomainFailureKind::Invalid,
        ],
        'a level nobody can be given' => [
            InvalidTeamLevel::unknown('admin'),
            'invalid_team_level',
            DomainFailureKind::Invalid,
        ],
        'the owner level offered to an invitee' => [
            InvalidTeamLevel::ownerNotAssignable(),
            'invalid_team_level',
            DomainFailureKind::Invalid,
        ],
        'an empty invitee email' => [
            InvalidTeamMemberEmail::empty(),
            'invalid_team_member_email',
            DomainFailureKind::Invalid,
        ],
        'an invitee email past the limit' => [
            InvalidTeamMemberEmail::tooLong(255),
            'invalid_team_member_email',
            DomainFailureKind::Invalid,
        ],
        'a malformed invitee email' => [
            InvalidTeamMemberEmail::malformed('grace'),
            'invalid_team_member_email',
            DomainFailureKind::Invalid,
        ],
        'an invitee email the account refused' => [
            InvalidTeamMemberEmail::rejectedByAccount(new RuntimeException('refused')),
            'invalid_team_member_email',
            DomainFailureKind::Invalid,
        ],
        'a team search past the limit' => [
            InvalidTeamSearch::tooLong(120),
            'invalid_team_search',
            DomainFailureKind::Invalid,
        ],
        'a change to the owner level' => [
            OwnerLevelIsFixed::for(StaffFixtures::MEMBER_ID),
            'owner_level_is_fixed',
            DomainFailureKind::Conflict,
        ],
        'removing the owner' => [
            OwnerCannotBeRemoved::for(StaffFixtures::MEMBER_ID),
            'owner_cannot_be_removed',
            DomainFailureKind::Conflict,
        ],
        'removing a member with appointments ahead' => [
            TeamMemberHasUpcomingAppointments::for(StaffFixtures::MEMBER_ID),
            'team_member_has_upcoming_appointments',
            DomainFailureKind::Conflict,
        ],
        'resending an invitation nobody is waiting on' => [
            TeamInvitationNotPending::for(StaffFixtures::MEMBER_ID),
            'team_invitation_not_pending',
            DomainFailureKind::Conflict,
        ],
        'an email already on the team' => [
            TeamMemberAlreadyExists::withEmail('grace@example.com'),
            'team_member_already_exists',
            DomainFailureKind::Invalid,
        ],
        'an account already on the team' => [
            TeamMemberAlreadyExists::forAccount(StaffFixtures::ACCOUNT_ID, new RuntimeException('unique violation')),
            'team_member_already_exists',
            DomainFailureKind::Invalid,
        ],
    ];
}

it('answers with the stable error code the client is shown a sentence for', function (
    DomainFailure $failure,
    string $code,
) {
    expect($failure->errorCode())->toBe($code);
})->with(staffFailures());

it('classifies the refusal so the edge knows which status to render', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
) {
    expect($failure->kind())->toBe($kind);
})->with(staffFailures());

it('carries the interface the renderer is registered against', function (DomainFailure $failure) {
    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure)->toBeInstanceOf(Throwable::class);
})->with(staffFailures());

it('has a sentence to show the caller in every locale', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
    string $locale,
) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][$code] ?? '')->toBeString()->not->toBe('');
})->with(staffFailures())->with(['en', 'es']);

it('says what it turned down and nothing more', function () {
    expect(StaffMemberNotFound::forAccount('a-1')->getMessage())
        ->toBe('Account [a-1] is not a staff member of this business.')
        ->and(StaffProfileNotFound::withId('p-1')->getMessage())
        ->toBe('Staff profile [p-1] was not found.')
        ->and(StaffProfileNotFound::forStaffMember('m-1')->getMessage())
        ->toBe('Staff member [m-1] has no profile.')
        ->and(InvalidProfileName::empty()->getMessage())
        ->toBe('A profile name cannot be empty.')
        ->and(InvalidProfileName::tooLong(255)->getMessage())
        ->toBe('A profile name takes up to [255] characters.')
        ->and(InvalidProfileJobTitle::tooLong(120)->getMessage())
        ->toBe('A job title takes up to [120] characters.')
        ->and(InvalidProfileAbout::tooLong(1000)->getMessage())
        ->toBe('A profile description takes up to [1000] characters.')
        ->and(ProfilePhotoTooLarge::atBytes(6, 5)->getMessage())
        ->toBe('A profile photo takes up to [5] bytes, got [6].')
        ->and(UnsupportedProfilePhoto::missing()->getMessage())
        ->toBe('No photo was offered.')
        ->and(UnsupportedProfilePhoto::ofType('image/gif')->getMessage())
        ->toBe('[image/gif] is not a supported profile photo type.');
});

it('keeps the cause when an account lookup is what turned the member down', function () {
    $cause = new RuntimeException('the account is gone');

    expect(StaffMemberNotFound::forAccount(StaffFixtures::ACCOUNT_ID, $cause)->getPrevious())->toBe($cause)
        ->and(StaffMemberNotFound::forAccount(StaffFixtures::ACCOUNT_ID)->getPrevious())->toBeNull();
});

it('keeps the account refusal as the cause of a refused name', function () {
    $refusal = InvalidAccountName::tooLong(255);

    expect(InvalidProfileName::rejectedByAccount($refusal)->getPrevious())->toBe($refusal);
});

it('tells a phone country it cannot dial apart from a number it cannot dial there', function () {
    expect(InvalidProfilePhone::inCountry('XX')->getMessage())
        ->toBe('[XX] is not a country we can dial.')
        ->and(InvalidProfilePhone::malformed('MX')->getMessage())
        ->toBe('The number offered cannot be dialled in [MX].')
        ->and(InvalidProfilePhone::forCountry(CountryCode::Mx)->getMessage())
        ->toBe(InvalidProfilePhone::malformed('MX')->getMessage());
});

it('tells a missing member apart from a member with no profile', function () {
    expect(StaffMemberNotFound::withId('m-1')->errorCode())
        ->not->toBe(StaffProfileNotFound::forStaffMember('m-1')->errorCode());
});

it('keeps the cause when the account side or the database is what refused a team member', function () {
    $cause = new RuntimeException('unique violation');

    expect(TeamMemberAlreadyExists::forAccount(StaffFixtures::ACCOUNT_ID, $cause)->getPrevious())->toBe($cause)
        ->and(InvalidTeamMemberEmail::rejectedByAccount($cause)->getPrevious())->toBe($cause);
});

it('names the member or the address each team refusal is about', function () {
    expect(TeamMemberAlreadyExists::withEmail('grace@example.com')->getMessage())
        ->toBe('[grace@example.com] is already a member of this business.')
        ->and(TeamMemberAlreadyExists::forAccount('a-1', new RuntimeException)->getMessage())
        ->toBe('Account [a-1] is already a member of this business.')
        ->and(OwnerCannotBeRemoved::for('m-1')->getMessage())
        ->toBe('Staff member [m-1] owns the business and cannot be removed from it.')
        ->and(TeamMemberHasUpcomingAppointments::for('m-1')->getMessage())
        ->toBe('Staff member [m-1] still has upcoming appointments.')
        ->and(InvalidTeamSearch::tooLong(120)->getMessage())
        ->toBe('A team search may not run past 120 characters.');
});
