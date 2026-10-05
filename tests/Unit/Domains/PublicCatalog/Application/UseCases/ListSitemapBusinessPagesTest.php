<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\UseCases\ListSitemapBusinessPages;
use App\Domains\PublicCatalog\Contracts\SitemapBusinesses;
use App\Domains\PublicCatalog\ValueObjects\SitemapBusinessPage;
use App\Shared\Contracts\BusinessContext;
use Tests\Support\PublicCatalog\FakeSitemapBusinesses;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

describe('listing the business pages a crawler may visit', function () {
    it('hands back every page the port published, in the order it gave them', function () {
        $ada = new SitemapBusinessPage(PublicCatalogFixtures::SLUG, new DateTimeImmutable('2026-03-29T01:30:00+00:00'));
        $ambar = new SitemapBusinessPage('peluqueria-ambar', null);

        $response = (new ListSitemapBusinessPages(new FakeSitemapBusinesses($ada, $ambar)))->handle();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe([$ada, $ambar]);
    });

    it('succeeds with an empty list when no business is published', function () {
        $response = (new ListSitemapBusinessPages(new FakeSitemapBusinesses))->handle();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe([]);
    });

    it('reads the port exactly once', function () {
        $businesses = new FakeSitemapBusinesses;

        (new ListSitemapBusinessPages($businesses))->handle();

        expect($businesses->reads)->toBe(1);
    });

    it('lets an infrastructure error out, because that is a bug and not an empty sitemap', function () {
        $bug = new RuntimeException('the businesses table is gone');
        $businesses = Mockery::mock(SitemapBusinesses::class);
        $businesses->shouldReceive('publishedPages')->once()->andThrow($bug);

        expect(fn () => (new ListSitemapBusinessPages($businesses))->handle())->toThrow($bug);
    });
});

describe('what a sitemap entry may reveal', function () {
    it('carries a slug and a date and nothing else, since it is printed for every crawler', function () {
        $properties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(SitemapBusinessPage::class))->getProperties(),
        );

        expect($properties)->toBe(['slug', 'lastModified']);
    });

    it('binds no business context, since the sitemap is deliberately cross-tenant', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(ListSitemapBusinessPages::class, '__construct'))->getParameters(),
        );

        expect($types)->not->toContain(BusinessContext::class);
    });
});
