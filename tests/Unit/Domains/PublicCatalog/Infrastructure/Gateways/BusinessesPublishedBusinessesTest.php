<?php

declare(strict_types=1);

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\Infrastructure\Gateways\BusinessesPublishedBusinesses;
use App\Domains\PublicCatalog\ValueObjects\PublicBusinessProfile;
use App\Shared\ValueObjects\CurrencyCode;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\FakeBusinessLogo;
use Tests\Support\Businesses\FakeBusinessRepository;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->businesses = new FakeBusinessRepository;
    $this->logo = new FakeBusinessLogo;

    $this->gateway = new BusinessesPublishedBusinesses($this->businesses, $this->logo);

    $this->publish = fn (
        string $id = PublicCatalogFixtures::BUSINESS_ID,
        string $slug = PublicCatalogFixtures::SLUG,
        ?string $about = 'Cortes y color desde 2019.',
    ) => $this->businesses->store(OnboardingFixtures::business(
        id: $id,
        name: PublicCatalogFixtures::NAME,
        slug: $slug,
        contactEmail: ContactEmail::fromString('hola@ada-salon.com'),
        about: About::fromNullable($about),
        currency: CurrencyCode::fromString(PublicCatalogFixtures::CURRENCY_CODE),
    ));

    $this->find = fn (string $slug = PublicCatalogFixtures::SLUG): PublicBusinessProfile => $this->gateway
        ->findBySlug($slug);
});

describe('describing the business a slug answers to', function () {
    it('translates the business on file into the profile a visitor reads', function () {
        ($this->publish)();
        $this->logo->store(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::LOGO_URL);

        $profile = ($this->find)();

        expect($profile)->toBeInstanceOf(PublicBusinessProfile::class)
            ->and($profile->id)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($profile->name)->toBe(PublicCatalogFixtures::NAME)
            ->and($profile->slug)->toBe(PublicCatalogFixtures::SLUG)
            ->and($profile->about)->toBe('Cortes y color desde 2019.')
            ->and($profile->timezone)->toBe(PublicCatalogFixtures::TIMEZONE)
            ->and($profile->currencyCode)->toBe(PublicCatalogFixtures::CURRENCY_CODE)
            ->and($profile->logoUrl)->toBe(PublicCatalogFixtures::LOGO_URL);
    });

    it('carries the business uuid, never an internal key', function () {
        ($this->publish)();

        $id = ($this->find)()->id;

        expect($id)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->toBeString()
            ->and(is_numeric($id))->toBeFalse();
    });

    it('asks for the logo under that same uuid', function () {
        ($this->publish)();

        ($this->find)();

        expect($this->logo->reads)->toBe([PublicCatalogFixtures::BUSINESS_ID]);
    });

    it('sends no logo for a business that uploaded none', function () {
        ($this->publish)();

        expect(($this->find)()->logoUrl)->toBeNull();
    });

    it('sends no description for a business that wrote none', function () {
        ($this->publish)(about: null);

        expect(($this->find)()->about)->toBeNull();
    });

    it('describes whichever business the slug answers to', function () {
        ($this->publish)();
        ($this->publish)(id: PublicCatalogFixtures::OTHER_BUSINESS_ID, slug: 'peluqueria-ambar');

        expect(($this->find)('peluqueria-ambar')->id)->toBe(PublicCatalogFixtures::OTHER_BUSINESS_ID)
            ->and(($this->find)()->id)->toBe(PublicCatalogFixtures::BUSINESS_ID);
    });

    it('publishes exactly the fields the public profile declares, so nothing private rides along', function () {
        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(PublicBusinessProfile::class))->getProperties(),
        );

        expect($fields)->toBe(['id', 'name', 'slug', 'about', 'timezone', 'currencyCode', 'logoUrl'])
            ->and($fields)->not->toContain('contactEmail')
            ->and($fields)->not->toContain('industryId');
    });
});

describe('a slug no business answers to', function () {
    it('refuses with its own domain failure, never the neighbour exception', function () {
        try {
            ($this->find)(PublicCatalogFixtures::UNKNOWN_SLUG);
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBeInstanceOf(BusinessPageNotFound::class)
            ->and($thrown)->not->toBeInstanceOf(BusinessNotFound::class)
            ->and($thrown->errorCode())->toBe('business_not_found')
            ->and($thrown->kind())->toBe(DomainFailureKind::NotFound);
    });

    it('keeps the neighbour refusal as the cause, so the trail survives the translation', function () {
        try {
            ($this->find)(PublicCatalogFixtures::UNKNOWN_SLUG);
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown->getPrevious())->toBeInstanceOf(BusinessNotFound::class)
            ->and($thrown->getMessage())
            ->toBe('No booking page answers to ['.PublicCatalogFixtures::UNKNOWN_SLUG.'].');
    });

    it('asks for no logo for a business it could not resolve', function () {
        expect(fn () => ($this->find)(PublicCatalogFixtures::UNKNOWN_SLUG))
            ->toThrow(BusinessPageNotFound::class)
            ->and($this->logo->reads)->toBe([]);
    });

    it('lets an infrastructure error out untouched, rather than dressing it as a missing page', function () {
        $bug = new RuntimeException('the businesses table is gone');

        $businesses = Mockery::mock(BusinessRepository::class);
        $businesses->shouldReceive('findBySlug')->once()->andThrow($bug);

        expect(fn () => (new BusinessesPublishedBusinesses($businesses, $this->logo))
            ->findBySlug(PublicCatalogFixtures::SLUG))->toThrow($bug);
    });
});

describe('confirming a slug exists', function () {
    it('answers yes for a published business and no for anything else', function () {
        ($this->publish)();

        expect($this->gateway->existsBySlug(PublicCatalogFixtures::SLUG))->toBeTrue()
            ->and($this->gateway->existsBySlug(PublicCatalogFixtures::UNKNOWN_SLUG))->toBeFalse();
    });

    it('reads no logo while it merely confirms', function () {
        ($this->publish)();

        $this->gateway->existsBySlug(PublicCatalogFixtures::SLUG);

        expect($this->logo->reads)->toBe([]);
    });
});
