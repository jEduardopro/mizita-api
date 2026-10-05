<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Contracts\SitemapBusinesses;
use App\Domains\PublicCatalog\ValueObjects\SitemapBusinessPage;
use App\Shared\Application\UseCaseResponse;

final class ListSitemapBusinessPages
{
    public function __construct(
        private readonly SitemapBusinesses $businesses,
    ) {}

    /**
     * @return UseCaseResponse<list<SitemapBusinessPage>>
     */
    public function handle(): UseCaseResponse
    {
        return UseCaseResponse::success($this->businesses->publishedPages());
    }
}
