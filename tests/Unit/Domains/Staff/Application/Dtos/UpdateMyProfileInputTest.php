<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\ProfilePhoneInput;
use App\Domains\Staff\Application\Dtos\UpdateMyProfileInput;
use App\Domains\Staff\Exceptions\InvalidProfileAbout;
use App\Domains\Staff\Exceptions\InvalidProfileJobTitle;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidProfilePhone;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\About;
use App\Domains\Staff\ValueObjects\JobTitle;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\PhoneNumbers;
use Tests\Support\Staff\StaffFixtures;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function myProfilePayload(array $overrides = []): array
{
    return [
        'name' => 'Ada Lovelace',
        'job_title' => StaffFixtures::JOB_TITLE,
        'about' => StaffFixtures::ABOUT,
        'phone' => ['country_code' => 'MX', 'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER],
        ...$overrides,
    ];
}

function myProfileRefusalFrom(UpdateMyProfileInput $input): ?Throwable
{
    try {
        $input->validate();
    } catch (Throwable $refusal) {
        return $refusal;
    }

    return null;
}

describe('building itself from a request payload', function () {
    it('assembles every field, the phone included, from a well formed payload', function () {
        $input = UpdateMyProfileInput::fromRequest(myProfilePayload(), StaffFixtures::ACCOUNT_ID);

        expect($input->accountId)->toBe(StaffFixtures::ACCOUNT_ID)
            ->and($input->name)->toBe('Ada Lovelace')
            ->and($input->jobTitle)->toBe(StaffFixtures::JOB_TITLE)
            ->and($input->about)->toBe(StaffFixtures::ABOUT)
            ->and($input->phone)->toBeInstanceOf(ProfilePhoneInput::class)
            ->and($input->phone?->countryCode)->toBe('MX')
            ->and($input->phone?->nationalNumber)->toBe(PhoneNumbers::MX_NATIONAL_NUMBER);
    });

    it('takes the account from the authenticated caller, never from the body', function () {
        $input = UpdateMyProfileInput::fromRequest(
            myProfilePayload(['account_id' => StaffFixtures::SECOND_ACCOUNT_ID, 'accountId' => StaffFixtures::SECOND_ACCOUNT_ID]),
            StaffFixtures::ACCOUNT_ID,
        );

        expect($input->accountId)->toBe(StaffFixtures::ACCOUNT_ID);
    });

    it('survives a payload with every key missing', function () {
        $input = UpdateMyProfileInput::fromRequest([], StaffFixtures::ACCOUNT_ID);

        expect($input->name)->toBe('')
            ->and($input->jobTitle)->toBeNull()
            ->and($input->about)->toBeNull()
            ->and($input->phone)->toBeNull();
    });

    it('turns a payload with every key missing into a domain failure, not a php error', function () {
        $refusal = myProfileRefusalFrom(UpdateMyProfileInput::fromRequest([], StaffFixtures::ACCOUNT_ID));

        expect($refusal)->toBeInstanceOf(InvalidProfileName::class)
            ->and($refusal)->toBeInstanceOf(DomainFailure::class);
    });

    it('reads a wrongly typed value as if it were missing', function (string $key, mixed $value) {
        $input = UpdateMyProfileInput::fromRequest(myProfilePayload([$key => $value]), StaffFixtures::ACCOUNT_ID);

        expect(match ($key) {
            'name' => $input->name,
            'job_title' => $input->jobTitle,
            'about' => $input->about,
            'phone' => $input->phone,
        })->toBe($key === 'name' ? '' : null);
    })->with([
        'a numeric name' => ['name', 42],
        'a name as a list' => ['name', ['Ada']],
        'a null name' => ['name', null],
        'a numeric job title' => ['job_title', 7],
        'a job title as a list' => ['job_title', ['Barbera']],
        'a boolean description' => ['about', true],
        'a phone as a string' => ['phone', '+525512345678'],
        'a phone as a number' => ['phone', 5512345678],
    ]);

    it('reads a blank or null optional field as a request to clear it', function (string $key, mixed $value) {
        $input = UpdateMyProfileInput::fromRequest(myProfilePayload([$key => $value]), StaffFixtures::ACCOUNT_ID);

        expect($key === 'job_title' ? $input->jobTitle : $input->about)->toBeNull();
    })->with([
        'a null job title' => ['job_title', null],
        'an empty job title' => ['job_title', ''],
        'a whitespace-only job title' => ['job_title', "  \t "],
        'a null description' => ['about', null],
        'an empty description' => ['about', ''],
        'a whitespace-only description' => ['about', "\n\n"],
    ]);

    it('reads an omitted, null or empty phone section as a request to clear the phone', function (array $payload) {
        expect(UpdateMyProfileInput::fromRequest($payload, StaffFixtures::ACCOUNT_ID)->phone)->toBeNull();
    })->with([
        'omitted' => [['name' => 'Ada']],
        'null' => [['name' => 'Ada', 'phone' => null]],
        'an empty section' => [['name' => 'Ada', 'phone' => []]],
    ]);

    it('fills in a half sent phone section rather than failing to read it', function () {
        $input = UpdateMyProfileInput::fromRequest(myProfilePayload(['phone' => ['country_code' => 'MX']]), StaffFixtures::ACCOUNT_ID);

        expect($input->phone?->countryCode)->toBe('MX')
            ->and($input->phone?->nationalNumber)->toBe('');
    });

    it('keeps the name as sent, leaving the trimming to the account', function () {
        expect(UpdateMyProfileInput::fromRequest(myProfilePayload(['name' => '  Ada  ']), StaffFixtures::ACCOUNT_ID)->name)
            ->toBe('  Ada  ');
    });
});

describe('validating itself', function () {
    it('returns silently for a well formed payload', function () {
        expect(myProfileRefusalFrom(UpdateMyProfileInput::fromRequest(myProfilePayload(), StaffFixtures::ACCOUNT_ID)))
            ->toBeNull();
    });

    it('returns silently for a name and nothing else', function () {
        expect(myProfileRefusalFrom(UpdateMyProfileInput::fromRequest(['name' => 'Ada'], StaffFixtures::ACCOUNT_ID)))
            ->toBeNull();
    });

    it('accepts every field at exactly its limit, counting characters rather than bytes', function () {
        $input = new UpdateMyProfileInput(
            accountId: StaffFixtures::ACCOUNT_ID,
            name: str_repeat('ñ', UpdateMyProfileInput::MAXIMUM_NAME_LENGTH),
            jobTitle: str_repeat('é', JobTitle::MAXIMUM_LENGTH),
            about: str_repeat('ü', About::MAXIMUM_LENGTH),
            phone: new ProfilePhoneInput('MX', str_repeat('5', ProfilePhoneInput::MAXIMUM_NATIONAL_NUMBER_LENGTH)),
        );

        expect(myProfileRefusalFrom($input))->toBeNull();
    });

    it('refuses what the form request would have refused', function (array $payload, string $accountId, string $exception) {
        $refusal = myProfileRefusalFrom(UpdateMyProfileInput::fromRequest($payload, $accountId));

        expect($refusal)->toBeInstanceOf($exception)
            ->and($refusal)->toBeInstanceOf(DomainFailure::class);
    })->with([
        'an account that is not a uuid' => [myProfilePayload(), 'not-a-uuid', StaffMemberNotFound::class],
        'an account that is a number' => [myProfilePayload(), '42', StaffMemberNotFound::class],
        'an empty account' => [myProfilePayload(), '', StaffMemberNotFound::class],
        'an account uuid with a trailing newline' => [myProfilePayload(), StaffFixtures::ACCOUNT_ID."\n", StaffMemberNotFound::class],
        'an empty name' => [myProfilePayload(['name' => '']), StaffFixtures::ACCOUNT_ID, InvalidProfileName::class],
        'a whitespace-only name' => [myProfilePayload(['name' => " \t\n"]), StaffFixtures::ACCOUNT_ID, InvalidProfileName::class],
        'a name one past the limit' => [myProfilePayload(['name' => str_repeat('a', 256)]), StaffFixtures::ACCOUNT_ID, InvalidProfileName::class],
        'a job title one past the limit' => [myProfilePayload(['job_title' => str_repeat('a', 121)]), StaffFixtures::ACCOUNT_ID, InvalidProfileJobTitle::class],
        'a description one past the limit' => [myProfilePayload(['about' => str_repeat('a', 1001)]), StaffFixtures::ACCOUNT_ID, InvalidProfileAbout::class],
        'a three letter country' => [myProfilePayload(['phone' => ['country_code' => 'MEX', 'national_number' => '5512345678']]), StaffFixtures::ACCOUNT_ID, InvalidProfilePhone::class],
        'a missing country' => [myProfilePayload(['phone' => ['national_number' => '5512345678']]), StaffFixtures::ACCOUNT_ID, InvalidProfilePhone::class],
        'a missing number' => [myProfilePayload(['phone' => ['country_code' => 'MX']]), StaffFixtures::ACCOUNT_ID, InvalidProfilePhone::class],
        'a whitespace-only number' => [myProfilePayload(['phone' => ['country_code' => 'MX', 'national_number' => '   ']]), StaffFixtures::ACCOUNT_ID, InvalidProfilePhone::class],
        'a number one past the limit' => [myProfilePayload(['phone' => ['country_code' => 'MX', 'national_number' => str_repeat('5', 25)]]), StaffFixtures::ACCOUNT_ID, InvalidProfilePhone::class],
    ]);

    it('names the limit when the name is too long', function () {
        $refusal = myProfileRefusalFrom(UpdateMyProfileInput::fromRequest(myProfilePayload(['name' => str_repeat('a', 256)]), StaffFixtures::ACCOUNT_ID));

        expect($refusal?->getMessage())->toBe('A profile name takes up to [255] characters.');
    });

    it('measures the name after trimming it', function () {
        $name = '  '.str_repeat('a', UpdateMyProfileInput::MAXIMUM_NAME_LENGTH).'  ';

        expect(myProfileRefusalFrom(UpdateMyProfileInput::fromRequest(myProfilePayload(['name' => $name]), StaffFixtures::ACCOUNT_ID)))
            ->toBeNull();
    });

    it('checks the account before anything the body carries', function () {
        $refusal = myProfileRefusalFrom(UpdateMyProfileInput::fromRequest(['name' => ''], 'not-a-uuid'));

        expect($refusal)->toBeInstanceOf(StaffMemberNotFound::class);
    });

    it('checks the name before the optional fields', function () {
        $refusal = myProfileRefusalFrom(UpdateMyProfileInput::fromRequest(
            ['name' => '', 'job_title' => str_repeat('a', 121)],
            StaffFixtures::ACCOUNT_ID,
        ));

        expect($refusal)->toBeInstanceOf(InvalidProfileName::class);
    });

    it('leaves the dialling of a two letter country it does not serve to the use case', function () {
        $input = UpdateMyProfileInput::fromRequest(
            myProfilePayload(['phone' => ['country_code' => 'XX', 'national_number' => '5512345678']]),
            StaffFixtures::ACCOUNT_ID,
        );

        expect(myProfileRefusalFrom($input))->toBeNull();
    });
});

describe('the value objects it hands the profile', function () {
    it('hands over the job title and the description trimmed', function () {
        $input = UpdateMyProfileInput::fromRequest(
            myProfilePayload(['job_title' => '  Colorista ', 'about' => "\nEspecialista en rubios.\n"]),
            StaffFixtures::ACCOUNT_ID,
        );

        expect($input->toJobTitle()?->value)->toBe('Colorista')
            ->and($input->toAbout()?->value)->toBe('Especialista en rubios.');
    });

    it('hands over nothing for a field that was left out', function () {
        $input = UpdateMyProfileInput::fromRequest(['name' => 'Ada'], StaffFixtures::ACCOUNT_ID);

        expect($input->toJobTitle())->toBeNull()
            ->and($input->toAbout())->toBeNull();
    });
});
