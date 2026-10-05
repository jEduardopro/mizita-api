<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Sitemap;

use App\Domains\PublicCatalog\ValueObjects\SitemapBusinessPage;
use App\Http\Seo\CanonicalUrls;
use App\Http\Seo\StaticPage;
use DateTimeImmutable;
use XMLWriter;

final class SitemapDocument
{
    private const SITEMAP_NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    private const XML_VERSION = '1.0';

    private const ENCODING = 'UTF-8';

    public function __construct(
        private readonly CanonicalUrls $urls,
    ) {}

    /**
     * @param  list<SitemapBusinessPage>  $businessPages
     */
    public function render(array $businessPages): string
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument(self::XML_VERSION, self::ENCODING);
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', self::SITEMAP_NAMESPACE);

        foreach (StaticPage::cases() as $page) {
            $this->writeUrl($writer, $this->urls->forStaticPage($page), null);
        }

        foreach ($businessPages as $page) {
            $this->writeUrl($writer, $this->urls->forBusinessPage($page->slug), $page->lastModified);
        }

        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    private function writeUrl(XMLWriter $writer, string $location, ?DateTimeImmutable $lastModified): void
    {
        $writer->startElement('url');
        $writer->writeElement('loc', $location);

        if ($lastModified !== null) {
            $writer->writeElement('lastmod', $lastModified->format(DATE_ATOM));
        }

        $writer->endElement();
    }
}
