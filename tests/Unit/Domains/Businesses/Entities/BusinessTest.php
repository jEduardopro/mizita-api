<?php

declare(strict_types=1);

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use Tests\Support\Businesses\OnboardingFixtures;

beforeEach(function () {
    $this->createBusiness = fn (string $name = OnboardingFixtures::NAME): Business => Business::create(
        id: OnboardingFixtures::GENERATED_BUSINESS_ID,
        name: $name,
        slug: Slug::fromName(OnboardingFixtures::NAME),
        industryId: OnboardingFixtures::INDUSTRY_ID,
        timezone: Timezone::fromString(OnboardingFixtures::TIMEZONE),
        now: OnboardingFixtures::now(),
    );
});

describe('creating a business', function () {
    it('holds everything it was created with', function () {
        $business = ($this->createBusiness)();

        expect($business->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($business->name())->toBe('Barbería Ñandú')
            ->and($business->slug())->toBe('barberia-nandu')
            ->and($business->industryId)->toBe(OnboardingFixtures::INDUSTRY_ID)
            ->and($business->timezone())->toBe('Europe/Madrid')
            ->and($business->createdAt)->toEqual(OnboardingFixtures::now());
    });

    it('hands its value objects out as the primitives a DTO is made of', function () {
        $business = ($this->createBusiness)();

        expect($business->slug())->toBeString()
            ->and($business->timezone())->toBeString();
    });

    it('trims the name', function () {
        expect(($this->createBusiness)('   Barbería Ñandú   ')->name())->toBe('Barbería Ñandú');
    });

    it('keeps accents and punctuation in the name, which is not a slug', function (string $name) {
        expect(($this->createBusiness)($name)->name())->toBe($name);
    })->with([
        'accents' => 'Barbería Ñandú',
        'punctuation' => 'Café & Té, S.L.',
        'another script' => 'Салон Красоты',
        'emoji' => 'Barbería 💈',
    ]);

    it('refuses a blank name', function (string $name) {
        expect(fn () => ($this->createBusiness)($name))
            ->toThrow(InvalidBusinessName::class, 'A business name cannot be empty.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
        'mixed whitespace' => " \t\n ",
    ]);

    it('accepts a single character, since only emptiness is refused', function () {
        expect(($this->createBusiness)('X')->name())->toBe('X');
    });
});

describe('rehydrating from storage', function () {
    it('skips the creation-time rules by design', function () {
        $business = Business::restore(
            id: OnboardingFixtures::GENERATED_BUSINESS_ID,
            name: '',
            slug: Slug::restore('Barberia_Nandu'),
            industryId: OnboardingFixtures::INDUSTRY_ID,
            timezone: Timezone::restore('europe/madrid'),
            createdAt: OnboardingFixtures::now(),
        );

        expect($business->name())->toBe('')
            ->and($business->slug())->toBe('Barberia_Nandu')
            ->and($business->timezone())->toBe('europe/madrid');
    });

    it('does not trim, because storage is reproduced rather than corrected', function () {
        $business = Business::restore(
            id: OnboardingFixtures::GENERATED_BUSINESS_ID,
            name: '  Barbería Ñandú  ',
            slug: Slug::restore(OnboardingFixtures::SLUG),
            industryId: OnboardingFixtures::INDUSTRY_ID,
            timezone: Timezone::restore(OnboardingFixtures::TIMEZONE),
            createdAt: OnboardingFixtures::now(),
        );

        expect($business->name())->toBe('  Barbería Ñandú  ');
    });

    it('keeps the stored creation instant instead of taking a new one', function () {
        $writtenAt = new DateTimeImmutable('2019-11-02T07:45:13+00:00');

        $business = Business::restore(
            id: OnboardingFixtures::GENERATED_BUSINESS_ID,
            name: OnboardingFixtures::NAME,
            slug: Slug::restore(OnboardingFixtures::SLUG),
            industryId: OnboardingFixtures::INDUSTRY_ID,
            timezone: Timezone::restore(OnboardingFixtures::TIMEZONE),
            createdAt: $writtenAt,
        );

        expect($business->createdAt)->toEqual($writtenAt)
            ->and($business->createdAt)->not->toEqual(OnboardingFixtures::now());
    });
});
