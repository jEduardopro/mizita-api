<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\BookingLinkData;
use App\Domains\Staff\Application\Dtos\BookingLinkStatus;
use App\Domains\Staff\Application\Dtos\MyProfileData;
use App\Domains\Staff\Infrastructure\Http\Resources\MyProfileResource;
use App\Domains\Staff\ValueObjects\BookingLinkBlocker;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\PhoneNumbers;
use Tests\Support\Staff\StaffFixtures;
use Tests\TestCase;

uses(TestCase::class);

function myProfileData(?BookingLinkStatus $bookingLink = null, bool $described = true): MyProfileData
{
    return new MyProfileData(
        id: StaffFixtures::PROFILE_ID,
        staffMemberId: StaffFixtures::MEMBER_ID,
        name: 'Ada Lovelace',
        email: 'ada@example.com',
        jobTitle: $described ? StaffFixtures::JOB_TITLE : null,
        about: $described ? StaffFixtures::ABOUT : null,
        phone: $described ? PhoneNumbers::mexican() : null,
        photoUrl: $described ? StaffFixtures::PHOTO_URL : null,
        bookingLink: $bookingLink ?? new BookingLinkStatus(
            new BookingLinkData(StaffFixtures::BOOKING_SLUG, StaffFixtures::BOOKING_URL),
            [],
        ),
        role: StaffRole::Owner,
        hasPassword: true,
    );
}

/**
 * @return array<string, mixed>
 */
function serializedMyProfile(MyProfileData $profile): array
{
    return (array) MyProfileResource::make($profile)->response()->getData(true)['data'];
}

it('serializes exactly the fields the profile screen reads, wrapped in data', function () {
    expect(serializedMyProfile(myProfileData()))->toBe([
        'id' => StaffFixtures::PROFILE_ID,
        'staff_member_id' => StaffFixtures::MEMBER_ID,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'job_title' => StaffFixtures::JOB_TITLE,
        'about' => StaffFixtures::ABOUT,
        'phone' => [
            'country_code' => 'MX',
            'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER,
            'e164' => PhoneNumbers::MX_E164,
        ],
        'photo_url' => StaffFixtures::PHOTO_URL,
        'booking_slug' => StaffFixtures::BOOKING_SLUG,
        'booking_url' => StaffFixtures::BOOKING_URL,
        'booking_link_blockers' => [],
        'role' => 'owner',
        'has_password' => true,
    ]);
});

it('identifies the profile and its member by uuid, never by a sequential key', function () {
    $profile = serializedMyProfile(myProfileData());

    expect($profile['id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and($profile['staff_member_id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and($profile)->not->toHaveKeys(['business_id', 'account_id']);
});

it('sends null for every optional detail the profile lacks', function () {
    $profile = serializedMyProfile(myProfileData(new BookingLinkStatus(null, []), described: false));

    expect($profile['job_title'])->toBeNull()
        ->and($profile['about'])->toBeNull()
        ->and($profile['phone'])->toBeNull()
        ->and($profile['photo_url'])->toBeNull()
        ->and($profile['booking_slug'])->toBeNull()
        ->and($profile['booking_url'])->toBeNull();
});

it('sends every blocker as its stable code, in the order it was assessed', function () {
    $profile = serializedMyProfile(myProfileData(new BookingLinkStatus(
        null,
        [BookingLinkBlocker::NoServices, BookingLinkBlocker::NoWorkingHours],
    )));

    expect($profile['booking_link_blockers'])->toBe(['no_services', 'no_working_hours']);
});
