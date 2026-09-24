<?php

declare(strict_types=1);

use App\Domains\Phones\Application\UseCases\AttachPhone;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Domains\Staff\Infrastructure\Gateways\PhonesStaffPhoneBook;
use App\Shared\ValueObjects\PhoneNumber;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Support\PhoneNumbers;
use Tests\Support\Staff\StaffFixtures;

const STAFF_PHONE_BOOK_PHONE_ID = '01930000-0000-7000-8000-0000000000f7';

function staffProfilePhoneRecord(?PhoneNumber $number = null): Phone
{
    return Phone::restore(
        id: STAFF_PHONE_BOOK_PHONE_ID,
        ownerType: PhoneOwnerType::StaffProfile,
        ownerId: StaffFixtures::PROFILE_ID,
        number: $number ?? PhoneNumbers::mexican(),
        createdAt: StaffFixtures::now(),
    );
}

beforeEach(function () {
    $this->phones = Mockery::mock(PhoneRepository::class);

    $this->phoneBook = new PhonesStaffPhoneBook(
        new AttachPhone(
            $this->phones,
            new FixedIdGenerator(STAFF_PHONE_BOOK_PHONE_ID),
            new FakeClock(StaffFixtures::now()),
        ),
        $this->phones,
    );
});

describe('reading the phone of a profile', function () {
    it('reads the number filed under the profile as its owner', function () {
        $this->phones->shouldReceive('findForOwner')->once()
            ->with(PhoneOwnerType::StaffProfile, StaffFixtures::PROFILE_ID)
            ->andReturn(staffProfilePhoneRecord());

        expect($this->phoneBook->forProfile(StaffFixtures::PROFILE_ID)?->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('answers with no number for a profile that has none', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturn(null);

        expect($this->phoneBook->forProfile(StaffFixtures::PROFILE_ID))->toBeNull();
    });
});

describe('replacing the phone of a profile', function () {
    it('attaches a first number to a profile that had none', function () {
        $this->phones->shouldReceive('findForOwner')->once()
            ->with(PhoneOwnerType::StaffProfile, StaffFixtures::PROFILE_ID)
            ->andReturn(null);

        $saved = null;
        $this->phones->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $this->phoneBook->replaceForProfile(StaffFixtures::PROFILE_ID, PhoneNumbers::mexican());

        expect($saved)->toBeInstanceOf(Phone::class)
            ->and($saved->id)->toBe(STAFF_PHONE_BOOK_PHONE_ID)
            ->and($saved->ownerType)->toBe(PhoneOwnerType::StaffProfile)
            ->and($saved->ownerId)->toBe(StaffFixtures::PROFILE_ID)
            ->and($saved->number()->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('changes the number the profile already had, keeping its record', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturn(staffProfilePhoneRecord());

        $saved = null;
        $this->phones->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $this->phoneBook->replaceForProfile(StaffFixtures::PROFILE_ID, PhoneNumbers::american());

        expect($saved->id)->toBe(STAFF_PHONE_BOOK_PHONE_ID)
            ->and($saved->number()->e164())->toBe(PhoneNumbers::US_E164);
    });

    it('deletes the phone of the profile when handed none, attaching nothing', function () {
        $this->phones->shouldReceive('deleteForOwner')->once()
            ->with(PhoneOwnerType::StaffProfile, StaffFixtures::PROFILE_ID);
        $this->phones->shouldNotReceive('save');
        $this->phones->shouldNotReceive('findForOwner');

        $this->phoneBook->replaceForProfile(StaffFixtures::PROFILE_ID, null);
    });
});
