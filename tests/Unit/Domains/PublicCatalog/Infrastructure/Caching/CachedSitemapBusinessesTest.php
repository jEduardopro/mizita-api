<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Caching\CachedSitemapBusinesses;
use App\Domains\PublicCatalog\ValueObjects\SitemapBusinessPage;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Tests\Support\PublicCatalog\FakeSitemapBusinesses;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

const CACHED_SITEMAP_TTL_SECONDS = 3600;

beforeEach(function () {
    $this->cache = new Repository(new ArrayStore(serializesValues: true));
    $this->cacheKey = (new ReflectionClassConstant(CachedSitemapBusinesses::class, 'CACHE_KEY'))->getValue();

    $this->adaPage = new SitemapBusinessPage(
        PublicCatalogFixtures::SLUG,
        new DateTimeImmutable('2026-03-29T01:30:00', new DateTimeZone('UTC')),
    );
    $this->ambarPage = new SitemapBusinessPage('peluqueria-ambar', null);

    $this->source = new FakeSitemapBusinesses($this->adaPage, $this->ambarPage);

    $this->cached = fn (?FakeSitemapBusinesses $source = null): CachedSitemapBusinesses => new CachedSitemapBusinesses(
        $source ?? $this->source,
        $this->cache,
        CACHED_SITEMAP_TTL_SECONDS,
    );

    $this->summarise = static fn (array $pages): array => array_map(
        static fn (SitemapBusinessPage $page): array => [$page->slug, $page->lastModified?->format(DATE_ATOM)],
        $pages,
    );
});

describe('the first read', function () {
    it('asks the source and hands its pages back', function () {
        $pages = ($this->cached)()->publishedPages();

        expect($this->source->reads)->toBe(1)
            ->and(($this->summarise)($pages))->toBe([
                [PublicCatalogFixtures::SLUG, '2026-03-29T01:30:00+00:00'],
                ['peluqueria-ambar', null],
            ]);
    });

    it('stores primitives only, so the entry survives a deploy that renames a class', function () {
        ($this->cached)()->publishedPages();

        expect($this->cache->get($this->cacheKey))->toBe([
            ['slug' => PublicCatalogFixtures::SLUG, 'last_modified' => '2026-03-29T01:30:00+00:00'],
            ['slug' => 'peluqueria-ambar', 'last_modified' => null],
        ]);
    });

    it('caches for the ttl it was configured with', function () {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('remember')->once()
            ->with($this->cacheKey, CACHED_SITEMAP_TTL_SECONDS, Mockery::type(Closure::class))
            ->andReturnUsing(static fn (string $key, int $ttl, Closure $compute): array => $compute());

        $pages = (new CachedSitemapBusinesses($this->source, $cache, CACHED_SITEMAP_TTL_SECONDS))->publishedPages();

        expect($pages)->toHaveCount(2);
    });
});

describe('every read after it', function () {
    it('answers from the cache without asking the source again', function () {
        $sitemap = ($this->cached)();

        $sitemap->publishedPages();
        $second = $sitemap->publishedPages();

        expect($this->source->reads)->toBe(1)
            ->and(($this->summarise)($second))->toBe([
                [PublicCatalogFixtures::SLUG, '2026-03-29T01:30:00+00:00'],
                ['peluqueria-ambar', null],
            ]);
    });

    it('shares the entry across instances, as one request after another would', function () {
        ($this->cached)()->publishedPages();
        ($this->cached)()->publishedPages();

        expect($this->source->reads)->toBe(1);
    });

    it('hands back value objects again, not the cached arrays', function () {
        ($this->cached)()->publishedPages();

        expect(($this->cached)()->publishedPages())->each->toBeInstanceOf(SitemapBusinessPage::class);
    });

    it('keeps the same instant for a page modified on the night the clocks go back', function () {
        $madrid = new DateTimeZone('Europe/Madrid');
        $firstHalfPastTwo = (new DateTimeImmutable('2026-10-25T00:30:00+00:00'))->setTimezone($madrid);
        $secondHalfPastTwo = (new DateTimeImmutable('2026-10-25T01:30:00+00:00'))->setTimezone($madrid);
        $source = new FakeSitemapBusinesses(
            new SitemapBusinessPage(PublicCatalogFixtures::SLUG, $firstHalfPastTwo),
            new SitemapBusinessPage('peluqueria-ambar', $secondHalfPastTwo),
        );

        ($this->cached)($source)->publishedPages();
        $restored = ($this->cached)($source)->publishedPages();

        expect($restored[0]->lastModified?->getTimestamp())->toBe($firstHalfPastTwo->getTimestamp())
            ->and($restored[1]->lastModified?->getTimestamp())->toBe($secondHalfPastTwo->getTimestamp())
            ->and($restored[0]->lastModified?->getTimestamp())->not->toBe($restored[1]->lastModified?->getTimestamp());
    });

    it('keeps the same instant for a page modified on the morning the clocks go forward', function () {
        $justAfterTheGap = (new DateTimeImmutable('2026-03-29T01:00:00+00:00'))->setTimezone(new DateTimeZone('Europe/Madrid'));
        $source = new FakeSitemapBusinesses(new SitemapBusinessPage(PublicCatalogFixtures::SLUG, $justAfterTheGap));

        ($this->cached)($source)->publishedPages();

        expect(($this->cached)($source)->publishedPages()[0]->lastModified?->format(DATE_ATOM))
            ->toBe('2026-03-29T03:00:00+02:00');
    });
});

describe('a catalogue with nothing published', function () {
    it('caches the empty list too, rather than asking the source on every crawl', function () {
        $empty = new FakeSitemapBusinesses;

        ($this->cached)($empty)->publishedPages();
        $second = ($this->cached)($empty)->publishedPages();

        expect($second)->toBe([])
            ->and($empty->reads)->toBe(1);
    });
});
