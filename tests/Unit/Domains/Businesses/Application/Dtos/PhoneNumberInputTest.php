<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\OnboardBusinessInput;
use App\Domains\Businesses\Application\Dtos\PhoneNumberInput;
use App\Domains\Businesses\Exceptions\UnsupportedPhoneNumber;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Businesses\OnboardingFixtures;

it('carries the two facts the caller typed, judging neither', function () {
    $phone = new PhoneNumberInput('mx', ' (55) 1234-5678 ');

    expect($phone->countryCode)->toBe('mx')
        ->and($phone->nationalNumber)->toBe(' (55) 1234-5678 ');
});

describe('validating the shape of a submitted number', function () {
    it('accepts a two letter country and a plausible national number', function (string $countryCode, string $nationalNumber) {
        expect(fn () => (new PhoneNumberInput($countryCode, $nationalNumber))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'a country we serve' => ['MX', '5512345678'],
        'the lowercase form of one we serve' => ['mx', '5512345678'],
        'a country we do not serve, because which ones we do is the use case verdict' => ['ES', '600123456'],
        'not a real country, because being on the ISO list is not a shape rule' => ['XX', '5512345678'],
        'separators, because stripping them is the parser job' => ['MX', ' (55) 1234-5678 '],
        'the longest national number allowed' => ['MX', str_repeat('5', 24)],
    ]);

    it('refuses a country code that is not exactly two characters', function (string $countryCode) {
        expect(fn () => (new PhoneNumberInput($countryCode, '5512345678'))->validate())
            ->toThrow(UnsupportedPhoneNumber::class, "[{$countryCode}] is not a country this platform operates in.");
    })->with([
        'empty' => '',
        'one letter' => 'M',
        'three letters' => 'MEX',
        'a dialling code' => '+52',
        'padded' => ' MX ',
    ]);

    it('refuses a national number that is blank or longer than a number can be', function (string $nationalNumber) {
        expect(fn () => (new PhoneNumberInput('MX', $nationalNumber))->validate())
            ->toThrow(UnsupportedPhoneNumber::class, 'The number offered for [MX] is not shaped like a phone number.');
    })->with([
        'empty' => '',
        'whitespace' => '   ',
        'tab' => "\t",
        'one character beyond the maximum' => str_repeat('5', 25),
    ]);

    it('rules on the country before the number, so a bad pair reports the country', function () {
        expect(fn () => (new PhoneNumberInput('MEX', ''))->validate())
            ->toThrow(UnsupportedPhoneNumber::class, '[MEX] is not a country this platform operates in.');
    });

    it('keeps the number itself out of the refusal, because these strings reach logs', function () {
        expect(fn () => (new PhoneNumberInput('MX', str_repeat('5', 25)))->validate())
            ->toThrow(UnsupportedPhoneNumber::class);

        try {
            (new PhoneNumberInput('MX', str_repeat('5', 25)))->validate();
        } catch (UnsupportedPhoneNumber $failure) {
            expect($failure->getMessage())->not->toContain('5555');
        }
    });

    it('classifies every refusal as a domain failure the edge can render', function () {
        $thrown = null;

        try {
            (new PhoneNumberInput('', ''))->validate();
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBeInstanceOf(DomainFailure::class);
    });
});

describe('the same rules reached through the signup payload that nests it', function () {
    it('refuses a nested phone the caller shaped wrongly', function (array $phone) {
        expect(fn () => OnboardBusinessInput::fromRequest([
            'name' => OnboardingFixtures::NAME,
            'timezone' => OnboardingFixtures::TIMEZONE,
            'industry_id' => OnboardingFixtures::INDUSTRY_ID,
            'phone' => $phone,
        ], OnboardingFixtures::OWNER_ACCOUNT_ID)->validate())->toThrow(UnsupportedPhoneNumber::class);
    })->with([
        'no country code' => [['national_number' => '5512345678']],
        'no national number' => [['country_code' => 'MX']],
        'a three letter country' => [['country_code' => 'MEX', 'national_number' => '5512345678']],
        'a national number past the bound' => [['country_code' => 'MX', 'national_number' => str_repeat('5', 25)]],
    ]);

    it('accepts a well shaped nested phone', function () {
        expect(fn () => OnboardBusinessInput::fromRequest([
            'name' => OnboardingFixtures::NAME,
            'timezone' => OnboardingFixtures::TIMEZONE,
            'industry_id' => OnboardingFixtures::INDUSTRY_ID,
            'phone' => ['country_code' => 'MX', 'national_number' => '5512345678'],
        ], OnboardingFixtures::OWNER_ACCOUNT_ID)->validate())->not->toThrow(Throwable::class);
    });

    it('validates nothing when the owner skipped the number entirely', function () {
        expect(fn () => OnboardBusinessInput::fromRequest([
            'name' => OnboardingFixtures::NAME,
            'timezone' => OnboardingFixtures::TIMEZONE,
            'industry_id' => OnboardingFixtures::INDUSTRY_ID,
        ], OnboardingFixtures::OWNER_ACCOUNT_ID)->validate())->not->toThrow(Throwable::class);
    });
});
