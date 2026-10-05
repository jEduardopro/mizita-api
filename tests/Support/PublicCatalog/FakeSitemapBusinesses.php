<?php

declare(strict_types=1);

namespace Tests\Support\PublicCatalog;

use App\Domains\PublicCatalog\Contracts\SitemapBusinesses;
use App\Domains\PublicCatalog\ValueObjects\SitemapBusinessPage;

final class FakeSitemapBusinesses implements SitemapBusinesses
{
    public int $reads = 0;

    /**
     * @var list<SitemapBusinessPage>
     */
    private array $pages;

    public function __construct(SitemapBusinessPage ...$pages)
    {
        $this->pages = array_values($pages);
    }

    public function publishedPages(): array
    {
        $this->reads++;

        return $this->pages;
    }
}
