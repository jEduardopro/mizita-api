<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Controllers;

use App\Domains\PublicCatalog\Application\UseCases\ListSitemapBusinessPages;
use App\Domains\PublicCatalog\Infrastructure\Http\Sitemap\SitemapDocument;
use App\Http\Controllers\Controller;
use App\Http\Seo\SearchIndexing;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    private const CONTENT_TYPE = 'application/xml; charset=UTF-8';

    public function __invoke(
        SearchIndexing $indexing,
        ListSitemapBusinessPages $listSitemapBusinessPages,
        SitemapDocument $document,
    ): Response {
        abort_unless($indexing->permitted(), Response::HTTP_NOT_FOUND);

        $businessPages = $listSitemapBusinessPages->handle()->value();

        return response($document->render($businessPages), Response::HTTP_OK, ['Content-Type' => self::CONTENT_TYPE]);
    }
}
