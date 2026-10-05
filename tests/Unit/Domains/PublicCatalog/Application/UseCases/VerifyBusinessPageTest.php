<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\VerifyBusinessPageInput;
use App\Domains\PublicCatalog\Application\UseCases\VerifyBusinessPage;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\ValueObjects\BusinessPageSlug;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);

    $this->verify = fn (string $slug = PublicCatalogFixtures::SLUG): UseCaseResponse => (new VerifyBusinessPage($this->businesses))
        ->handle(new VerifyBusinessPageInput($slug));
});

describe('a slug a published business answers to', function () {
    it('lets the visitor into the booking flow', function () {
        $this->businesses->shouldReceive('existsBySlug')->once()->with(PublicCatalogFixtures::SLUG)->andReturnTrue();

        $response = ($this->verify)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('asks the port about exactly the slug the url carried', function () {
        $asked = null;
        $this->businesses->shouldReceive('existsBySlug')->once()->with(Mockery::capture($asked))->andReturnTrue();

        ($this->verify)('peluqueria-ambar');

        expect($asked)->toBe('peluqueria-ambar');
    });

    it('only confirms, and never loads the profile it would have to describe', function () {
        $this->businesses->shouldReceive('existsBySlug')->once()->andReturnTrue();
        $this->businesses->shouldNotReceive('findBySlug');
        $this->businesses->shouldNotReceive('identifyBySlug');
        $this->businesses->shouldNotReceive('originalLogoUrlFor');

        expect(($this->verify)()->succeeded())->toBeTrue();
    });
});

describe('a slug no published business answers to', function () {
    beforeEach(function () {
        $this->businesses->shouldReceive('existsBySlug')->once()
            ->with(PublicCatalogFixtures::UNKNOWN_SLUG)
            ->andReturnFalse();
    });

    it('answers with a not found refusal instead of throwing it', function () {
        $response = ($this->verify)(PublicCatalogFixtures::UNKNOWN_SLUG);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('names the slug it refused in the developer message', function () {
        expect(fn () => ($this->verify)(PublicCatalogFixtures::UNKNOWN_SLUG)->value())
            ->toThrow('No booking page answers to ['.PublicCatalogFixtures::UNKNOWN_SLUG.'].');
    });
});

describe('a slug no business could own', function () {
    it('refuses it as not found before asking the port', function (string $slug) {
        $this->businesses->shouldNotReceive('existsBySlug');

        $response = ($this->verify)($slug);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    })->with([
        'empty' => '',
        'uppercase' => 'Ada-Salon',
        'unicode' => 'peluquería-ñandú',
        'a path' => '../ada-salon',
        'one past the maximum length' => str_repeat('a', BusinessPageSlug::MAXIMUM_LENGTH + 1),
    ]);
});

describe('what is not a refusal', function () {
    it('lets an infrastructure error out, because that is a bug and not a 404', function () {
        $bug = new RuntimeException('the businesses table is gone');
        $this->businesses->shouldReceive('existsBySlug')->once()->andThrow($bug);

        expect(fn () => ($this->verify)())->toThrow($bug);
    });
});

describe('the tenant a booking flow runs under', function () {
    it('binds no business context, since the slug is a public name and not an entitlement', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(VerifyBusinessPage::class, '__construct'))->getParameters(),
        );

        expect($types)->toBe([PublishedBusinesses::class])
            ->and($types)->not->toContain(BusinessContext::class);
    });
});
