<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Http\Sitemap\SitemapDocument;
use App\Domains\PublicCatalog\ValueObjects\SitemapBusinessPage;
use App\Http\Seo\CanonicalUrls;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\TestCase;

uses(TestCase::class);

const SITEMAP_DOCUMENT_APP_URL = 'https://mizita.test';

const SITEMAP_DOCUMENT_NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

const SITEMAP_DOCUMENT_STATIC_LOCATIONS = [
    SITEMAP_DOCUMENT_APP_URL.'/',
    SITEMAP_DOCUMENT_APP_URL.'/terms',
    SITEMAP_DOCUMENT_APP_URL.'/privacy',
    SITEMAP_DOCUMENT_APP_URL.'/cookies',
];

/**
 * @return list<array{loc: string, lastmod: string|null}>
 */
function sitemapDocumentEntries(string $xml): array
{
    $document = new DOMDocument;
    $document->loadXML($xml);

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('s', SITEMAP_DOCUMENT_NAMESPACE);

    $entries = [];

    foreach ($xpath->query('/s:urlset/s:url') ?: [] as $url) {
        $lastmod = $xpath->query('s:lastmod', $url)?->item(0);

        $entries[] = [
            'loc' => (string) $xpath->query('s:loc', $url)?->item(0)?->textContent,
            'lastmod' => $lastmod?->textContent,
        ];
    }

    return $entries;
}

beforeEach(function () {
    config(['app.url' => SITEMAP_DOCUMENT_APP_URL]);

    $this->document = new SitemapDocument(new CanonicalUrls);
});

describe('the document a crawler downloads', function () {
    it('is a utf-8 xml document', function () {
        expect($this->document->render([]))->toStartWith('<?xml version="1.0" encoding="UTF-8"?>');
    });

    it('declares the sitemap protocol namespace on its urlset root', function () {
        $document = new DOMDocument;
        $document->loadXML($this->document->render([]));

        expect($document->documentElement?->localName)->toBe('urlset')
            ->and($document->documentElement?->namespaceURI)->toBe(SITEMAP_DOCUMENT_NAMESPACE);
    });
});

describe('the static pages', function () {
    it('lists the landing and the three legal pages with no business published', function () {
        expect(array_column(sitemapDocumentEntries($this->document->render([])), 'loc'))
            ->toBe(SITEMAP_DOCUMENT_STATIC_LOCATIONS);
    });

    it('claims no last modification it does not know', function () {
        expect(array_column(sitemapDocumentEntries($this->document->render([])), 'lastmod'))
            ->toBe([null, null, null, null])
            ->and($this->document->render([]))->not->toContain('<lastmod>');
    });
});

describe('the business pages', function () {
    it('lists every published page after the static ones, at its canonical url', function () {
        $xml = $this->document->render([
            new SitemapBusinessPage(PublicCatalogFixtures::SLUG, null),
            new SitemapBusinessPage('peluqueria-ambar', null),
        ]);

        expect(array_column(sitemapDocumentEntries($xml), 'loc'))->toBe([
            ...SITEMAP_DOCUMENT_STATIC_LOCATIONS,
            SITEMAP_DOCUMENT_APP_URL.'/'.PublicCatalogFixtures::SLUG,
            SITEMAP_DOCUMENT_APP_URL.'/peluqueria-ambar',
        ]);
    });

    it('prints the last modification as a utc atom timestamp', function () {
        $xml = $this->document->render([
            new SitemapBusinessPage(PublicCatalogFixtures::SLUG, new DateTimeImmutable('2026-03-29T01:30:00', new DateTimeZone('UTC'))),
        ]);

        expect(sitemapDocumentEntries($xml)[4]['lastmod'])->toBe('2026-03-29T01:30:00+00:00');
    });

    it('omits the last modification of a page that has none', function () {
        $xml = $this->document->render([new SitemapBusinessPage(PublicCatalogFixtures::SLUG, null)]);

        expect(sitemapDocumentEntries($xml)[4]['lastmod'])->toBeNull();
    });

    it('escapes a slug so the markup cannot be broken out of', function () {
        $slug = 'ada&salon<script>';

        $xml = $this->document->render([new SitemapBusinessPage($slug, null)]);

        expect($xml)->not->toContain('<script>')
            ->and($xml)->not->toContain('ada&salon')
            ->and(sitemapDocumentEntries($xml)[4]['loc'])->toBe((new CanonicalUrls)->forBusinessPage($slug));
    });

    it('lists one url per page, nothing merged or dropped', function () {
        $pages = array_map(
            static fn (int $index): SitemapBusinessPage => new SitemapBusinessPage('business-'.$index, null),
            range(1, 25),
        );

        expect(sitemapDocumentEntries($this->document->render($pages)))->toHaveCount(4 + 25);
    });
});
