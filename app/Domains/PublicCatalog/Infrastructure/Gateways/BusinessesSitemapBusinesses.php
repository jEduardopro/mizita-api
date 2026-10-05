<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\PublicCatalog\Contracts\SitemapBusinesses;
use App\Domains\PublicCatalog\ValueObjects\SitemapBusinessPage;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class BusinessesSitemapBusinesses implements SitemapBusinesses
{
    private const MAXIMUM_PAGES = 49_000;

    private const UTC = 'UTC';

    /**
     * @var list<string>
     */
    private const EXPOSED_COLUMNS = ['slug', 'created_at', 'updated_at'];

    public function publishedPages(): array
    {
        return BusinessModel::query()
            ->orderBy('id')
            ->limit(self::MAXIMUM_PAGES)
            ->get(self::EXPOSED_COLUMNS)
            ->map(static fn (BusinessModel $business): SitemapBusinessPage => new SitemapBusinessPage(
                slug: (string) $business->slug,
                lastModified: self::inUtc($business->updated_at ?? $business->created_at),
            ))
            ->values()
            ->all();
    }

    private static function inUtc(?DateTimeInterface $moment): ?DateTimeImmutable
    {
        if ($moment === null) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone(self::UTC));
    }
}
