<?php

declare(strict_types=1);

use App\Http\Seo\CanonicalUrls;
use App\Http\Seo\StaticPage;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

const CANONICAL_URLS_APP_URL = 'https://mizita.test';

beforeEach(function () {
    config(['app.url' => CANONICAL_URLS_APP_URL]);

    $this->urls = new CanonicalUrls;

    $this->visitedAs = function (string $url, array $server = []): void {
        $this->app->make('url')->setRequest(Request::create($url, 'GET', server: $server));
    };
});

describe('the address a search engine files a page under', function () {
    it('places every static page under the application url', function (StaticPage $page, string $expected) {
        expect($this->urls->forStaticPage($page))->toBe($expected);
    })->with([
        'landing' => [StaticPage::Landing, CANONICAL_URLS_APP_URL.'/'],
        'terms' => [StaticPage::Terms, CANONICAL_URLS_APP_URL.'/terms'],
        'privacy' => [StaticPage::Privacy, CANONICAL_URLS_APP_URL.'/privacy'],
        'cookies' => [StaticPage::Cookies, CANONICAL_URLS_APP_URL.'/cookies'],
    ]);

    it('places a business page at the site root, under its slug', function () {
        expect($this->urls->forBusinessPage('ada-salon'))->toBe(CANONICAL_URLS_APP_URL.'/ada-salon');
    });

    it('places the sitemap at the site root', function () {
        expect($this->urls->forSitemap())->toBe(CANONICAL_URLS_APP_URL.'/sitemap.xml');
    });

    it('collapses a trailing slash on the application url', function () {
        config(['app.url' => CANONICAL_URLS_APP_URL.'/']);

        expect($this->urls->forBusinessPage('ada-salon'))->toBe(CANONICAL_URLS_APP_URL.'/ada-salon')
            ->and($this->urls->forStaticPage(StaticPage::Landing))->toBe(CANONICAL_URLS_APP_URL.'/');
    });
});

describe('what the visit itself cannot change', function () {
    it('drops the query string the visitor arrived with', function () {
        ($this->visitedAs)(CANONICAL_URLS_APP_URL.'/ada-salon?utm_source=newsletter&ref=whatsapp');

        expect($this->urls->forBusinessPage('ada-salon'))->toBe(CANONICAL_URLS_APP_URL.'/ada-salon')
            ->and($this->urls->forBusinessPage('ada-salon'))->not->toContain('?');
    });

    it('keeps the application host when the request reached another one', function () {
        ($this->visitedAs)('http://10.0.0.12:8080/terms');

        expect($this->urls->forStaticPage(StaticPage::Terms))->toBe(CANONICAL_URLS_APP_URL.'/terms');
    });

    it('ignores a forwarded host even when every proxy is trusted', function () {
        Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO);

        try {
            $forged = Request::create(CANONICAL_URLS_APP_URL.'/terms', 'GET', server: [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_X_FORWARDED_HOST' => 'attacker.example',
                'HTTP_X_FORWARDED_PROTO' => 'http',
            ]);
            $this->app->make('url')->setRequest($forged);

            expect($forged->getHost())->toBe('attacker.example')
                ->and($this->urls->forStaticPage(StaticPage::Terms))->toBe(CANONICAL_URLS_APP_URL.'/terms')
                ->and($this->urls->forSitemap())->not->toContain('attacker.example');
        } finally {
            Request::setTrustedProxies([], -1);
        }
    });
});
