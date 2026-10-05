<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Caching;

use App\Domains\PublicCatalog\Contracts\SitemapBusinesses;
use App\Domains\PublicCatalog\ValueObjects\SitemapBusinessPage;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository;

final class CachedSitemapBusinesses implements SitemapBusinesses
{
    private const CACHE_KEY = 'public-catalog:sitemap:business-pages';

    public function __construct(
        private readonly SitemapBusinesses $source,
        private readonly Repository $cache,
        private readonly int $ttlSeconds,
    ) {}

    public function publishedPages(): array
    {
        /** @var list<array{slug: string, last_modified: string|null}> $cached */
        $cached = $this->cache->remember(
            self::CACHE_KEY,
            $this->ttlSeconds,
            fn (): array => array_map(self::toCacheable(...), $this->source->publishedPages()),
        );

        return array_map(self::fromCacheable(...), $cached);
    }

    /**
     * @return array{slug: string, last_modified: string|null}
     */
    private static function toCacheable(SitemapBusinessPage $page): array
    {
        return [
            'slug' => $page->slug,
            'last_modified' => $page->lastModified?->format(DATE_ATOM),
        ];
    }

    /**
     * @param  array{slug: string, last_modified: string|null}  $cached
     */
    private static function fromCacheable(array $cached): SitemapBusinessPage
    {
        return new SitemapBusinessPage(
            slug: $cached['slug'],
            lastModified: $cached['last_modified'] === null ? null : new DateTimeImmutable($cached['last_modified']),
        );
    }
}
