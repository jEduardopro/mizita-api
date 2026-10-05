<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\BusinessPageSharePreview;
use App\Domains\PublicCatalog\Application\Dtos\DescribeBusinessPageSharePreviewInput;
use App\Domains\PublicCatalog\Application\UseCases\DescribeBusinessPageSharePreview;
use App\Domains\PublicCatalog\Contracts\PublishedBanner;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedCity;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Addresses\AddressFixtures;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);
    $this->banner = Mockery::mock(PublishedBanner::class);
    $this->city = Mockery::mock(PublishedCity::class);

    $this->useCase = new DescribeBusinessPageSharePreview($this->businesses, $this->banner, $this->city);

    $this->describe = fn (string $slug = PublicCatalogFixtures::SLUG): UseCaseResponse => $this->useCase
        ->handle(new DescribeBusinessPageSharePreviewInput($slug));

    $this->optimizedLogoUrl = 'https://cdn.mizita.test/businesses/conversions/logo-optimized.webp';

    $this->publish = function (
        ?string $about = 'Cortes y color desde 2019.',
        ?string $bannerUrl = PublicCatalogFixtures::BANNER_URL,
        ?string $originalLogoUrl = PublicCatalogFixtures::LOGO_URL,
        ?string $city = AddressFixtures::CITY,
    ): void {
        $this->businesses->shouldReceive('findBySlug')->once()
            ->with(PublicCatalogFixtures::SLUG)
            ->andReturn(PublicCatalogFixtures::profile(
                about: $about,
                logoUrl: $originalLogoUrl === null ? null : $this->optimizedLogoUrl,
            ));
        $this->businesses->shouldReceive('originalLogoUrlFor')
            ->with(PublicCatalogFixtures::BUSINESS_ID)
            ->andReturn($originalLogoUrl);
        $this->banner->shouldReceive('originalUrlForBusiness')->once()
            ->with(PublicCatalogFixtures::BUSINESS_ID)
            ->andReturn($bannerUrl);
        $this->city->shouldReceive('forBusiness')->once()
            ->with(PublicCatalogFixtures::BUSINESS_ID)
            ->andReturn($city);
    };
});

describe('a published business shared on a chat or a timeline', function () {
    it('describes the page with its name, its city, its about and its banner', function () {
        ($this->publish)();

        $response = ($this->describe)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeInstanceOf(BusinessPageSharePreview::class)
            ->and($response->value()->name)->toBe(PublicCatalogFixtures::NAME)
            ->and($response->value()->city)->toBe(AddressFixtures::CITY)
            ->and($response->value()->about)->toBe('Cortes y color desde 2019.')
            ->and($response->value()->imageUrl)->toBe(PublicCatalogFixtures::BANNER_URL);
    });

    it('asks for the banner and the city under the business uuid the profile carried', function () {
        $bannerBusinessId = null;
        $cityBusinessId = null;

        $this->businesses->shouldReceive('findBySlug')->once()->andReturn(PublicCatalogFixtures::profile());
        $this->businesses->shouldReceive('originalLogoUrlFor')->once()->andReturnNull();
        $this->banner->shouldReceive('originalUrlForBusiness')->once()
            ->with(Mockery::capture($bannerBusinessId))
            ->andReturnNull();
        $this->city->shouldReceive('forBusiness')->once()
            ->with(Mockery::capture($cityBusinessId))
            ->andReturnNull();

        ($this->describe)();

        expect($bannerBusinessId)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($cityBusinessId)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and(is_numeric($bannerBusinessId))->toBeFalse();
    });

    it('looks the business up under whichever slug the url carried', function () {
        $this->businesses->shouldReceive('findBySlug')->once()
            ->with('peluqueria-ambar')
            ->andReturn(PublicCatalogFixtures::profile(slug: 'peluqueria-ambar'));
        $this->businesses->shouldReceive('originalLogoUrlFor')->once()->andReturnNull();
        $this->banner->shouldReceive('originalUrlForBusiness')->once()->andReturnNull();
        $this->city->shouldReceive('forBusiness')->once()->andReturnNull();

        expect(($this->describe)('peluqueria-ambar')->succeeded())->toBeTrue();
    });

    it('hands back no identity, because the preview is printed into public html', function () {
        $properties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(BusinessPageSharePreview::class))->getProperties(),
        );

        expect($properties)->toBe(['name', 'city', 'about', 'imageUrl']);
    });
});

describe('the image a shared link unfurls with', function () {
    it('prefers the uploaded banner over the uploaded logo', function () {
        ($this->publish)(bannerUrl: PublicCatalogFixtures::BANNER_URL, originalLogoUrl: PublicCatalogFixtures::LOGO_URL);

        expect(($this->describe)()->value()->imageUrl)->toBe(PublicCatalogFixtures::BANNER_URL);
    });

    it('asks for no logo once the banner answered', function () {
        $this->businesses->shouldReceive('findBySlug')->once()->andReturn(PublicCatalogFixtures::profile());
        $this->businesses->shouldNotReceive('originalLogoUrlFor');
        $this->banner->shouldReceive('originalUrlForBusiness')->once()->andReturn(PublicCatalogFixtures::BANNER_URL);
        $this->city->shouldReceive('forBusiness')->once()->andReturnNull();

        expect(($this->describe)()->value()->imageUrl)->toBe(PublicCatalogFixtures::BANNER_URL);
    });

    it('falls back to the uploaded logo, never the compressed copy the profile carries', function () {
        ($this->publish)(bannerUrl: null, originalLogoUrl: PublicCatalogFixtures::LOGO_URL);

        $imageUrl = ($this->describe)()->value()->imageUrl;

        expect($imageUrl)->toBe(PublicCatalogFixtures::LOGO_URL)
            ->and($imageUrl)->not->toBe($this->optimizedLogoUrl);
    });

    it('asks for the uploaded logo under the business uuid the profile carried', function () {
        $logoBusinessId = null;

        $this->businesses->shouldReceive('findBySlug')->once()->andReturn(PublicCatalogFixtures::profile());
        $this->businesses->shouldReceive('originalLogoUrlFor')->once()
            ->with(Mockery::capture($logoBusinessId))
            ->andReturnNull();
        $this->banner->shouldReceive('originalUrlForBusiness')->once()->andReturnNull();
        $this->city->shouldReceive('forBusiness')->once()->andReturnNull();

        ($this->describe)();

        expect($logoBusinessId)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and(is_numeric($logoBusinessId))->toBeFalse();
    });

    it('uses the banner for a business that uploaded no logo', function () {
        ($this->publish)(bannerUrl: PublicCatalogFixtures::BANNER_URL, originalLogoUrl: null);

        expect(($this->describe)()->value()->imageUrl)->toBe(PublicCatalogFixtures::BANNER_URL);
    });

    it('offers no image for a business that uploaded neither', function () {
        ($this->publish)(bannerUrl: null, originalLogoUrl: null);

        expect(($this->describe)()->value()->imageUrl)->toBeNull();
    });
});

describe('what a business left blank', function () {
    it('passes no city through for a business that filed no address', function () {
        ($this->publish)(city: null);

        $preview = ($this->describe)()->value();

        expect($preview->city)->toBeNull()
            ->and($preview->name)->toBe(PublicCatalogFixtures::NAME);
    });

    it('passes no about through for a business that wrote none', function () {
        ($this->publish)(about: null);

        expect(($this->describe)()->value()->about)->toBeNull();
    });
});

describe('a slug no published business answers to', function () {
    beforeEach(function () {
        $this->businesses->shouldReceive('findBySlug')->once()
            ->with(PublicCatalogFixtures::UNKNOWN_SLUG)
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));
    });

    it('answers with a not found refusal instead of throwing it', function () {
        $response = ($this->describe)(PublicCatalogFixtures::UNKNOWN_SLUG);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('asks neither the banner, the logo nor the city about a business it never found', function () {
        $this->banner->shouldNotReceive('originalUrlForBusiness');
        $this->businesses->shouldNotReceive('originalLogoUrlFor');
        $this->city->shouldNotReceive('forBusiness');

        ($this->describe)(PublicCatalogFixtures::UNKNOWN_SLUG);
    });

    it('carries no preview a caller could read past the refusal', function () {
        expect(fn () => ($this->describe)(PublicCatalogFixtures::UNKNOWN_SLUG)->value())
            ->toThrow('No booking page answers to ['.PublicCatalogFixtures::UNKNOWN_SLUG.'].');
    });
});

describe('a slug no business could own', function () {
    it('refuses it as not found before asking any port', function (string $slug) {
        $this->businesses->shouldNotReceive('findBySlug');
        $this->businesses->shouldNotReceive('originalLogoUrlFor');
        $this->banner->shouldNotReceive('originalUrlForBusiness');
        $this->city->shouldNotReceive('forBusiness');

        $response = ($this->describe)($slug);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    })->with([
        'empty' => '',
        'uppercase' => 'Ada-Salon',
        'unicode' => 'peluquería-ñandú',
        'too long' => str_repeat('a', 61),
    ]);
});

describe('what is not a refusal', function () {
    it('lets an infrastructure error out, because that is a bug and not a 404', function () {
        $bug = new RuntimeException('the businesses table is gone');

        $this->businesses->shouldReceive('findBySlug')->once()->andThrow($bug);

        expect(fn () => ($this->describe)())->toThrow($bug);
    });
});

describe('the tenant a public preview runs under', function () {
    it('binds no business context, since the slug is a public name and not an entitlement', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(DescribeBusinessPageSharePreview::class, '__construct'))->getParameters(),
        );

        expect($types)->toBe([PublishedBusinesses::class, PublishedBanner::class, PublishedCity::class])
            ->and($types)->not->toContain(BusinessContext::class);
    });
});
