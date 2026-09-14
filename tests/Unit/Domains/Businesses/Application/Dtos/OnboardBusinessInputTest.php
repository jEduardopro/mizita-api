<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\OnboardBusinessInput;
use App\Domains\Businesses\Application\Dtos\PhoneNumberInput;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\Exceptions\InvalidBusinessOwner;
use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\Exceptions\UnsupportedPhoneNumber;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Businesses\OnboardingFixtures;

/**
 * @return array<string, mixed>
 */
function mizitaOnboardingPayload(array $overrides = []): array
{
    return [
        'name' => OnboardingFixtures::NAME,
        'timezone' => OnboardingFixtures::TIMEZONE,
        'industry_id' => OnboardingFixtures::INDUSTRY_ID,
        ...$overrides,
    ];
}

it('resolves every field the form request lets through', function () {
    $input = OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload(),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    );

    expect($input->name)->toBe(OnboardingFixtures::NAME)
        ->and($input->timezone)->toBe(OnboardingFixtures::TIMEZONE)
        ->and($input->industryId)->toBe(OnboardingFixtures::INDUSTRY_ID)
        ->and($input->ownerAccountId)->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID)
        ->and($input->phone)->toBeNull();
});

it('assembles the nested phone from the two facts the client sent', function () {
    $input = OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload(['phone' => ['country_code' => 'MX', 'national_number' => '5512345678']]),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    );

    expect($input->phone)->toBeInstanceOf(PhoneNumberInput::class)
        ->and($input->phone->countryCode)->toBe('MX')
        ->and($input->phone->nationalNumber)->toBe('5512345678');
});

it('carries the number exactly as it was typed, judging nothing', function () {
    $input = OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload(['phone' => ['country_code' => 'mx', 'national_number' => ' (55) 1234-5678 ']]),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    );

    expect($input->phone->countryCode)->toBe('mx')
        ->and($input->phone->nationalNumber)->toBe(' (55) 1234-5678 ');
});

it('reads no phone at all when the owner skipped it', function (array $payload) {
    expect(OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload($payload),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    )->phone)->toBeNull();
})->with([
    'the key is absent' => [[]],
    'the key is null' => [['phone' => null]],
    'the object is empty' => [['phone' => []]],
]);

it('takes the owner from its argument and never from the payload', function (array $smuggled) {
    $input = OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload($smuggled),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    );

    expect($input->ownerAccountId)->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID);
})->with([
    'owner_account_id' => [['owner_account_id' => 'someone-elses-account']],
    'ownerAccountId' => [['ownerAccountId' => 'someone-elses-account']],
    'owner' => [['owner' => 'someone-elses-account']],
]);

it('ignores an address the client tried to choose for itself', function () {
    $input = OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload(['slug' => 'a-slug-i-picked']),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    );

    expect($input)->toEqual(OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload(),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    ));
});

describe('validating the signup payload', function () {
    it('accepts a well formed signup', function () {
        expect(fn () => OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload(['phone' => ['country_code' => 'MX', 'national_number' => '5512345678']]),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        )->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a signup naming no owner account', function (string $ownerAccountId) {
        expect(fn () => OnboardBusinessInput::fromRequest(mizitaOnboardingPayload(), $ownerAccountId)->validate())
            ->toThrow(InvalidBusinessOwner::class, 'A business cannot be onboarded without an owner account.');
    })->with([
        'empty' => '',
        'whitespace' => '   ',
        'tab' => "\t",
    ]);

    it('refuses a name outside the bounds the form request also enforces', function (string $name) {
        expect(fn () => OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload(['name' => $name]),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        )->validate())->toThrow(InvalidBusinessName::class);
    })->with([
        'empty' => '',
        'whitespace' => '   ',
        'one character' => 'B',
        'one character beyond the maximum' => str_repeat('a', 121),
    ]);

    it('accepts a name at either bound', function (string $name) {
        expect(fn () => OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload(['name' => $name]),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        )->validate())->not->toThrow(Throwable::class);
    })->with([
        'the shortest allowed' => 'Bo',
        'the longest allowed' => str_repeat('a', 120),
        'the longest allowed in accented characters' => str_repeat('ñ', 120),
    ]);

    it('refuses a time zone that is not an IANA identifier', function (string $timezone) {
        expect(fn () => OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload(['timezone' => $timezone]),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        )->validate())->toThrow(InvalidBusinessTimezone::class);
    })->with([
        'empty' => '',
        'the wrong case' => 'europe/madrid',
        'a zone that does not exist' => 'Mars/Olympus',
        'a fixed offset' => '+02:00',
        'a city that is not a zone' => 'Europe/Barcelona',
    ]);

    it('accepts a backward compatible alias, because a browser reporting one is reporting a zone that works', function (string $timezone) {
        expect(fn () => OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload(['timezone' => $timezone]),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        )->validate())->not->toThrow(Throwable::class);
    })->with([
        'a renamed zone' => 'Asia/Calcutta',
        'an abbreviation PHP still lists' => 'CET',
        'UTC' => 'UTC',
    ]);

    it('refuses an industry id that is not uuid shaped', function (string $industryId) {
        expect(fn () => OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload(['industry_id' => $industryId]),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        )->validate())->toThrow(UnknownIndustry::class, "Industry [{$industryId}] is not in the catalog.");
    })->with([
        'empty' => '',
        'a word' => 'barbershop',
        'a bare integer' => '7',
        'a uuid missing a group' => '01930000-0000-7000-000000000000',
        'a uuid with a trailing newline' => "01930000-0000-7000-8000-0000000000f1\n",
        'a uuid with something appended' => '01930000-0000-7000-8000-0000000000f1-extra',
    ]);

    it('accepts a uuid in either case, because the catalog is what rules on membership', function (string $industryId) {
        expect(fn () => OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload(['industry_id' => $industryId]),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        )->validate())->not->toThrow(Throwable::class);
    })->with([
        'lowercase' => '01930000-0000-7000-8000-0000000000f1',
        'uppercase' => '01930000-0000-7000-8000-0000000000F1',
        'a uuid the catalog has never heard of' => '01930000-0000-7000-8000-0000000000ff',
    ]);

    it('reports the owner first when the payload is wrong in several ways at once', function () {
        expect(fn () => OnboardBusinessInput::fromRequest([
            'name' => '',
            'timezone' => 'Mars/Olympus',
            'industry_id' => 'barbershop',
            'phone' => ['country_code' => 'MEX', 'national_number' => ''],
        ], '')->validate())->toThrow(InvalidBusinessOwner::class);
    });

    it('refuses a payload with every key missing as a domain failure, not a PHP error', function () {
        $thrown = null;

        try {
            OnboardBusinessInput::fromRequest([], OnboardingFixtures::OWNER_ACCOUNT_ID)->validate();
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBeInstanceOf(InvalidBusinessName::class)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class);
    });

    it('refuses a payload with every key missing and no owner either', function () {
        $thrown = null;

        try {
            OnboardBusinessInput::fromRequest([], '')->validate();
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBeInstanceOf(InvalidBusinessOwner::class)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class);
    });
});

describe('reading a payload whose fields are not the strings the form request would have let through', function () {
    it('refuses an unreadable field with the rule that owns it, not with a verdict about the next field', function (array $payload, string $failure) {
        expect(fn () => OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload($payload),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        )->validate())->toThrow($failure);
    })->with([
        'a name sent as an array' => [['name' => [OnboardingFixtures::NAME]], InvalidBusinessName::class],
        'a name sent as a number' => [['name' => 42], InvalidBusinessName::class],
        'a name sent as a boolean' => [['name' => true], InvalidBusinessName::class],
        'a time zone sent as an array' => [['timezone' => [OnboardingFixtures::TIMEZONE]], InvalidBusinessTimezone::class],
        'a time zone sent as an offset in minutes' => [['timezone' => 120], InvalidBusinessTimezone::class],
        'an industry sent as an array' => [['industry_id' => [OnboardingFixtures::INDUSTRY_ID]], UnknownIndustry::class],
        'an industry sent as a number' => [['industry_id' => 7], UnknownIndustry::class],
    ]);

    it('names the empty name it refused when the client sent an array for one', function () {
        expect(fn () => OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload(['name' => [OnboardingFixtures::NAME]]),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        )->validate())->toThrow(InvalidBusinessName::class, 'A business name cannot be empty.');
    });

    it('refuses an unreadable phone part as a phone', function (array $phone) {
        expect(fn () => OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload(['phone' => $phone]),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        )->validate())->toThrow(UnsupportedPhoneNumber::class);
    })->with([
        'a country code sent as an array' => [['country_code' => ['MX'], 'national_number' => '5512345678']],
        'a country code sent as a dialling prefix' => [['country_code' => 52, 'national_number' => '5512345678']],
        'a national number sent as a number' => [['country_code' => 'MX', 'national_number' => 5512345678]],
        'both parts unreadable' => [['country_code' => 52, 'national_number' => 5512345678]],
    ]);

    it('reads a phone it cannot parse as a phone that was offered, not as no phone at all', function (mixed $phone) {
        $input = OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload(['phone' => $phone]),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        );

        expect($input->phone)->toBeInstanceOf(PhoneNumberInput::class)
            ->and($input->phone->countryCode)->toBe('')
            ->and($input->phone->nationalNumber)->toBe('');
    })->with([
        'a bare string where the object belongs' => '5512345678',
        'a number' => 5512345678,
        'a boolean' => true,
    ]);

    it('tells a signup that offered no phone apart from one that offered a phone which is not real', function () {
        $skipped = OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload(),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        );
        $unreadable = OnboardBusinessInput::fromRequest(
            mizitaOnboardingPayload(['phone' => 'call the shop']),
            OnboardingFixtures::OWNER_ACCOUNT_ID,
        );

        expect($skipped->phone)->toBeNull()
            ->and($unreadable->phone)->not->toBeNull()
            ->and(fn () => $skipped->validate())->not->toThrow(Throwable::class)
            ->and(fn () => $unreadable->validate())->toThrow(UnsupportedPhoneNumber::class);
    });
});
