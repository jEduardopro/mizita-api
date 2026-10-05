<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\SitemapBusinessPage;

interface SitemapBusinesses
{
    /**
     * @return list<SitemapBusinessPage>
     */
    public function publishedPages(): array;
}
