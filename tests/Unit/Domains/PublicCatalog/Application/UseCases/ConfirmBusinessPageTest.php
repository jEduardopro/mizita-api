<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\BusinessPageIdentity;
use App\Domains\PublicCatalog\Application\Dtos\ConfirmBusinessPageInput;
use App\Domains\PublicCatalog\Application\UseCases\ConfirmBusinessPage;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);

    $this->useCase = new ConfirmBusinessPage($this->businesses);

    $this->confirm = fn (string $slug = PublicCatalogFixtures::SLUG): UseCaseResponse => $this->useCase
        ->handle(new ConfirmBusinessPageInput($slug));
});

describe('confirming a slug the front end is about to render', function () {
    it('answers with the slug it confirmed', function () {
        $this->businesses->shouldReceive('existsBySlug')->once()
            ->with(PublicCatalogFixtures::SLUG)
            ->andReturnTrue();

        $response = ($this->confirm)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeInstanceOf(BusinessPageIdentity::class)
            ->and($response->value()->slug)->toBe(PublicCatalogFixtures::SLUG);
    });

    it('answers about whichever slug the url carried', function () {
        $this->businesses->shouldReceive('existsBySlug')->once()
            ->with('peluqueria-ambar')
            ->andReturnTrue();

        expect(($this->confirm)('peluqueria-ambar')->value()->slug)->toBe('peluqueria-ambar');
    });

    it('asks only whether the page exists, never loading it', function () {
        $this->businesses->shouldReceive('existsBySlug')->once()->andReturnTrue();
        $this->businesses->shouldNotReceive('findBySlug');

        ($this->confirm)();
    });

    it('hands back no business id, because a name is all the web route may know', function () {
        $properties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(BusinessPageIdentity::class))->getProperties(),
        );

        expect($properties)->toBe(['slug']);
    });
});

describe('a slug no published business answers to', function () {
    it('answers with a refusal instead of throwing it', function () {
        $this->businesses->shouldReceive('existsBySlug')->once()->andReturnFalse();

        $response = ($this->confirm)(PublicCatalogFixtures::UNKNOWN_SLUG);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('refuses a slug that answers to nothing, whatever shape it arrived in', function (string $slug) {
        $this->businesses->shouldReceive('existsBySlug')->once()->with($slug)->andReturnFalse();

        expect(($this->confirm)($slug)->failed())->toBeTrue();
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a reserved word' => 'admin',
        'an unknown name' => PublicCatalogFixtures::UNKNOWN_SLUG,
        'unicode' => 'peluquería-ñandú',
    ]);

    it('carries no identity a caller could read past the refusal', function () {
        $this->businesses->shouldReceive('existsBySlug')->once()->andReturnFalse();

        expect(fn () => ($this->confirm)(PublicCatalogFixtures::UNKNOWN_SLUG)->value())
            ->toThrow('No booking page answers to ['.PublicCatalogFixtures::UNKNOWN_SLUG.'].');
    });
});

describe('what is not a refusal', function () {
    it('lets an infrastructure error out, because that is a bug and not a 404', function () {
        $bug = new RuntimeException('the businesses table is gone');

        $this->businesses->shouldReceive('existsBySlug')->once()->andThrow($bug);

        expect(fn () => ($this->confirm)())->toThrow($bug);
    });
});

describe('the tenant a public confirmation runs under', function () {
    it('binds no business context, since the slug is a public name and not an entitlement', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(ConfirmBusinessPage::class, '__construct'))->getParameters(),
        );

        expect($types)->toBe([PublishedBusinesses::class])
            ->and($types)->not->toContain(BusinessContext::class);
    });
});
