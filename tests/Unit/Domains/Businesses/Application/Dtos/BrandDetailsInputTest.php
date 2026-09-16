<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\BrandDetailsInput;
use App\Domains\Businesses\Exceptions\InvalidBusinessAbout;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\Exceptions\InvalidBusinessSlug;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\ValueObjects\About;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\Businesses\SettingsFixtures;

it('accepts the brand as the form paints it', function () {
    expect(fn () => SettingsFixtures::brand()->validate())->not->toThrow(Throwable::class);
});

it('holds what it was handed without normalising it, because the use case trims on the way out', function () {
    $brand = SettingsFixtures::brand(name: '  Barbería Ñandú  ');

    expect($brand->name)->toBe('  Barbería Ñandú  ')
        ->and($brand->slug)->toBe(OnboardingFixtures::SLUG)
        ->and($brand->industryId)->toBe(OnboardingFixtures::INDUSTRY_ID)
        ->and($brand->about)->toBe(SettingsFixtures::ABOUT);
});

describe('the name', function () {
    it('accepts a name a business would actually carry', function (string $name) {
        expect(fn () => SettingsFixtures::brand(name: $name)->validate())->not->toThrow(Throwable::class);
    })->with([
        'accents' => 'Barbería Ñandú',
        'punctuation' => 'Café & Té, S.L.',
        'another script' => 'Салон Красоты',
        'emoji' => 'Barbería 💈',
        'the shortest allowed' => 'Ax',
        'the longest allowed' => str_repeat('a', BrandDetailsInput::MAXIMUM_NAME_LENGTH),
        'padded to the longest allowed' => '  '.str_repeat('a', BrandDetailsInput::MAXIMUM_NAME_LENGTH).'  ',
    ]);

    it('refuses a name a business could not be found by', function (string $name) {
        expect(fn () => SettingsFixtures::brand(name: $name)->validate())->toThrow(InvalidBusinessName::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'a single letter' => 'A',
        'a single letter padded' => '  A  ',
        'one past the maximum' => str_repeat('a', BrandDetailsInput::MAXIMUM_NAME_LENGTH + 1),
    ]);

    it('measures the name in characters, so accents do not shorten it', function () {
        expect(fn () => SettingsFixtures::brand(
            name: str_repeat('ñ', BrandDetailsInput::MAXIMUM_NAME_LENGTH),
        )->validate())->not->toThrow(Throwable::class);
    });

    it('bounds the name by the constants the form request shares with it', function () {
        expect(BrandDetailsInput::MINIMUM_NAME_LENGTH)->toBe(2)
            ->and(BrandDetailsInput::MAXIMUM_NAME_LENGTH)->toBe(120);
    });
});

describe('the slug', function () {
    it('accepts an address the booking page could live at', function (string $slug) {
        expect(fn () => SettingsFixtures::brand(slug: $slug)->validate())->not->toThrow(Throwable::class);
    })->with([
        'a single word' => 'barberia',
        'hyphenated' => 'barberia-nandu',
        'with digits' => 'barberia-2019',
        'padded' => '  barberia-nandu  ',
    ]);

    it('refuses an address the booking page could not live at', function (string $slug) {
        expect(fn () => SettingsFixtures::brand(slug: $slug)->validate())->toThrow(InvalidBusinessSlug::class);
    })->with([
        'empty' => '',
        'uppercase' => 'Barberia',
        'accents' => 'barbería',
        'spaces inside' => 'barberia nandu',
        'a leading hyphen' => '-barberia',
        'a trailing hyphen' => 'barberia-',
        'doubled hyphens' => 'barberia--nandu',
        'a slash' => 'barberia/nandu',
        'one past the maximum length' => str_repeat('a', 61),
    ]);

    it('refuses a slug the application has already claimed for itself', function (string $reserved) {
        expect(fn () => SettingsFixtures::brand(slug: $reserved)->validate())
            ->toThrow(InvalidBusinessSlug::class);
    })->with([
        'settings' => 'settings',
        'api' => 'api',
        'admin' => 'admin',
        'me' => 'me',
    ]);
});

describe('the industry', function () {
    it('accepts a uuid in either case, because the catalog is what rules on membership', function (string $industryId) {
        expect(fn () => SettingsFixtures::brand(industryId: $industryId)->validate())->not->toThrow(Throwable::class);
    })->with([
        'lowercase' => OnboardingFixtures::INDUSTRY_ID,
        'uppercase' => mb_strtoupper(OnboardingFixtures::INDUSTRY_ID),
    ]);

    it('refuses an industry id that is not uuid shaped', function (string $industryId) {
        expect(fn () => SettingsFixtures::brand(industryId: $industryId)->validate())
            ->toThrow(UnknownIndustry::class, "Industry [{$industryId}] is not in the catalog.");
    })->with([
        'empty' => '',
        'a row number' => '7',
        'a word' => 'barbershop',
        'a uuid missing a group' => '01930000-0000-7000-8000',
        'a uuid with a bad character' => '01930000-0000-7000-8000-00000000000g',
        'a uuid with no hyphens' => '019300000000700080000000000000f1',
        'a padded uuid' => ' 01930000-0000-7000-8000-0000000000f1 ',
        'a uuid with a trailing newline' => "01930000-0000-7000-8000-0000000000f1\n",
    ]);
});

describe('the description', function () {
    it('accepts a business that says nothing about itself', function (?string $about) {
        expect(fn () => SettingsFixtures::brand(about: $about)->validate())->not->toThrow(Throwable::class);
    })->with([
        'null' => null,
        'empty' => '',
        'whitespace only' => '   ',
    ]);

    it('accepts a description up to the length the column holds', function () {
        expect(fn () => SettingsFixtures::brand(about: str_repeat('a', About::MAXIMUM_LENGTH))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses a description one character past what the column holds', function () {
        expect(fn () => SettingsFixtures::brand(about: str_repeat('a', About::MAXIMUM_LENGTH + 1))->validate())
            ->toThrow(InvalidBusinessAbout::class);
    });
});

it('names the first problem it finds, in the order a reader fills the form', function (array $overrides, string $failure) {
    expect(fn () => SettingsFixtures::brand(...$overrides)->validate())->toThrow($failure);
})->with([
    'the name before the slug' => [['name' => '', 'slug' => 'NOPE'], InvalidBusinessName::class],
    'the slug before the industry' => [['slug' => 'NOPE', 'industryId' => '7'], InvalidBusinessSlug::class],
    'the industry before the description' => [
        ['industryId' => '7', 'about' => str_repeat('a', About::MAXIMUM_LENGTH + 1)],
        UnknownIndustry::class,
    ],
]);
