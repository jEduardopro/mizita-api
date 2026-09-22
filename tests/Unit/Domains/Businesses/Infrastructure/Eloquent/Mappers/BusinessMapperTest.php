<?php

declare(strict_types=1);

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Infrastructure\Eloquent\Mappers\BusinessMapper;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
use App\Shared\ValueObjects\CurrencyCode;
use Tests\Support\Businesses\OnboardingFixtures;

const BUSINESS_MAPPER_INDUSTRY_KEY = 13;

/**
 * @param  array<string, mixed>  $overrides
 */
function businessRow(array $overrides = []): BusinessModel
{
    $model = new BusinessModel;

    $model->setRawAttributes([
        'id' => 9,
        'uuid' => OnboardingFixtures::GENERATED_BUSINESS_ID,
        'name' => OnboardingFixtures::NAME,
        'slug' => OnboardingFixtures::SLUG,
        'industry_id' => BUSINESS_MAPPER_INDUSTRY_KEY,
        'timezone' => OnboardingFixtures::TIMEZONE,
        'contact_email' => 'hola@barberia.com',
        'about' => 'Barbería clásica desde 2019.',
        'currency_code' => 'MXN',
        'created_at' => OnboardingFixtures::now(),
        ...$overrides,
    ], true);

    $industry = new IndustryModel;
    $industry->setRawAttributes([
        'id' => BUSINESS_MAPPER_INDUSTRY_KEY,
        'uuid' => OnboardingFixtures::INDUSTRY_ID,
    ], true);

    $model->setRelation('industry', $industry);

    return $model;
}

describe('reading a row', function () {
    it('restores every value the row carries', function () {
        $business = (new BusinessMapper)->toEntity(businessRow());

        expect($business)->toBeInstanceOf(Business::class)
            ->and($business->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($business->name())->toBe(OnboardingFixtures::NAME)
            ->and($business->slug())->toBe(OnboardingFixtures::SLUG)
            ->and($business->timezone())->toBe(OnboardingFixtures::TIMEZONE)
            ->and($business->contactEmail())->toBe('hola@barberia.com')
            ->and($business->about())->toBe('Barbería clásica desde 2019.')
            ->and($business->currency())->toBe('MXN')
            ->and($business->createdAt)->toEqual(OnboardingFixtures::now());
    });

    it('takes the industry as the uuid on the relation, never the key the row holds', function () {
        $business = (new BusinessMapper)->toEntity(businessRow());

        expect($business->industryId())->toBe(OnboardingFixtures::INDUSTRY_ID)
            ->and($business->industryId())->not->toBe((string) BUSINESS_MAPPER_INDUSTRY_KEY);
    });

    it('carries the uuid as its identity, never the primary key', function () {
        $business = (new BusinessMapper)->toEntity(businessRow());

        expect($business->id)->toBeString()
            ->and($business->id)->not->toBe('9');
    });

    it('restores a business that filled neither optional field', function () {
        $business = (new BusinessMapper)->toEntity(businessRow(['contact_email' => null, 'about' => null]));

        expect($business->contactEmail())->toBeNull()
            ->and($business->about())->toBeNull();
    });

    it('restores each optional field on its own', function (string $column, string $reader) {
        $business = (new BusinessMapper)->toEntity(businessRow([$column => null]));

        expect($business->{$reader}())->toBeNull();
    })->with([
        'no contact email' => ['contact_email', 'contactEmail'],
        'no description' => ['about', 'about'],
    ]);

    it('restores a row without holding it to the invariants creation enforces', function () {
        $business = (new BusinessMapper)->toEntity(businessRow([
            'name' => '',
            'contact_email' => 'hola@BARBERIA',
            'about' => '   ',
            'currency_code' => 'mxn',
        ]));

        expect($business->name())->toBe('')
            ->and($business->contactEmail())->toBe('hola@BARBERIA')
            ->and($business->about())->toBe('   ')
            ->and($business->currency())->toBe('mxn');
    });

    it('keeps unicode in the description exactly as the row stores it', function () {
        $about = "Barbería Ñandú 💈\nDesde 2019.";

        expect((new BusinessMapper)->toEntity(businessRow(['about' => $about]))->about())->toBe($about);
    });
});

describe('writing a row', function () {
    it('writes exactly the columns the row owns', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(
                contactEmail: ContactEmail::fromString('hola@barberia.com'),
                about: About::fromString('Barbería clásica desde 2019.'),
                currency: CurrencyCode::fromString('MXN'),
            ),
            BUSINESS_MAPPER_INDUSTRY_KEY,
        );

        expect($attributes)->toBe([
            'uuid' => OnboardingFixtures::GENERATED_BUSINESS_ID,
            'name' => OnboardingFixtures::NAME,
            'slug' => OnboardingFixtures::SLUG,
            'industry_id' => BUSINESS_MAPPER_INDUSTRY_KEY,
            'timezone' => OnboardingFixtures::TIMEZONE,
            'contact_email' => 'hola@barberia.com',
            'about' => 'Barbería clásica desde 2019.',
            'currency_code' => 'MXN',
        ]);
    });

    it('writes the industry as the key it was handed, never as the uuid the entity carries', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
        );

        expect($attributes['industry_id'])->toBe(BUSINESS_MAPPER_INDUSTRY_KEY)
            ->toBeInt()
            ->and($attributes['industry_id'])->not->toBe(OnboardingFixtures::INDUSTRY_ID);
    });

    it('writes null for the optional fields a business never filled', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
        );

        expect($attributes['contact_email'])->toBeNull()
            ->and($attributes['about'])->toBeNull();
    });

    it('writes the default currency for a business that never chose one', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
        );

        expect($attributes['currency_code'])->toBe('MXN')->toBeString();
    });

    it('writes the currency folded, as the value object normalised it', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(currency: CurrencyCode::fromString('usd')),
            BUSINESS_MAPPER_INDUSTRY_KEY,
        );

        expect($attributes['currency_code'])->toBe('USD');
    });

    it('leaves the identity and the timestamps to the database', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
        );

        expect($attributes)->not->toHaveKey('id')
            ->and($attributes)->not->toHaveKey('created_at')
            ->and($attributes)->not->toHaveKey('updated_at')
            ->and($attributes)->not->toHaveKey('deleted_at');
    });

    it('names the columns as the table spells them, not as the entity does', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
        );

        expect($attributes)->toHaveKeys(['contact_email', 'about', 'currency_code'])
            ->and($attributes)->not->toHaveKey('currency')
            ->and($attributes)->not->toHaveKey('contactEmail');
    });
});

it('carries a business through both directions unchanged', function () {
    $mapper = new BusinessMapper;

    $attributes = $mapper->toAttributes(
        OnboardingFixtures::business(
            contactEmail: ContactEmail::fromString('  Hola@BARBERIA.com '),
            about: About::fromString('  Barbería clásica desde 2019.  '),
            currency: CurrencyCode::fromString('usd'),
        ),
        BUSINESS_MAPPER_INDUSTRY_KEY,
    );

    $restored = $mapper->toEntity(businessRow([...$attributes, 'created_at' => OnboardingFixtures::now()]));

    expect($restored->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
        ->and($restored->name())->toBe(OnboardingFixtures::NAME)
        ->and($restored->slug())->toBe(OnboardingFixtures::SLUG)
        ->and($restored->industryId())->toBe(OnboardingFixtures::INDUSTRY_ID)
        ->and($restored->timezone())->toBe(OnboardingFixtures::TIMEZONE)
        ->and($restored->contactEmail())->toBe('Hola@barberia.com')
        ->and($restored->about())->toBe('Barbería clásica desde 2019.')
        ->and($restored->currency())->toBe('USD');
});

it('carries a business with nothing optional filled through both directions unchanged', function () {
    $mapper = new BusinessMapper;

    $attributes = $mapper->toAttributes(OnboardingFixtures::business(), BUSINESS_MAPPER_INDUSTRY_KEY);
    $restored = $mapper->toEntity(businessRow([...$attributes, 'created_at' => OnboardingFixtures::now()]));

    expect($restored->contactEmail())->toBeNull()
        ->and($restored->about())->toBeNull()
        ->and($restored->currency())->toBe('MXN');
});
