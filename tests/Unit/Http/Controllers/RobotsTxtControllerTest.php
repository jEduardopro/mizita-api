<?php

declare(strict_types=1);

use App\Http\Controllers\RobotsTxtController;
use App\Http\Seo\CanonicalUrls;
use App\Http\Seo\SearchIndexing;
use Illuminate\Http\Response;
use Tests\TestCase;

uses(TestCase::class);

const ROBOTS_TXT_APP_URL = 'https://mizita.test';

beforeEach(function () {
    config(['app.url' => ROBOTS_TXT_APP_URL]);

    $this->robots = fn (): Response => (new RobotsTxtController)(new SearchIndexing, new CanonicalUrls);

    $this->lines = fn (): array => explode("\n", rtrim((string) ($this->robots)()->getContent(), "\n"));
});

describe('the file a crawler reads first', function () {
    it('answers as utf-8 plain text', function (bool $indexable) {
        config(['seo.indexable' => $indexable]);

        $response = ($this->robots)();

        expect($response->getStatusCode())->toBe(Response::HTTP_OK)
            ->and($response->headers->get('Content-Type'))->toBe('text/plain; charset=UTF-8');
    })->with(['closed' => false, 'open' => true]);

    it('ends with a newline', function (bool $indexable) {
        config(['seo.indexable' => $indexable]);

        expect((string) ($this->robots)()->getContent())->toEndWith("\n");
    })->with(['closed' => false, 'open' => true]);
});

describe('a site closed to search engines', function () {
    beforeEach(function () {
        config(['seo.indexable' => false]);
    });

    it('turns every crawler away from every path', function () {
        expect(($this->robots)()->getContent())->toBe("User-agent: *\nDisallow: /\n");
    });

    it('advertises no sitemap, since the sitemap answers 404', function () {
        expect(($this->robots)()->getContent())->not->toContain('Sitemap:');
    });
});

describe('a site open to search engines', function () {
    beforeEach(function () {
        config(['seo.indexable' => true]);
    });

    it('addresses every crawler and allows the site by default', function () {
        expect(array_slice(($this->lines)(), 0, 2))->toBe(['User-agent: *', 'Allow: /']);
    });

    it('never disallows the whole site', function () {
        expect(($this->lines)())->not->toContain('Disallow: /');
    });

    it('closes a private area at its exact path and below it, never at a shared prefix', function (string $path) {
        $lines = ($this->lines)();

        expect($lines)->toContain('Disallow: '.$path.'$')
            ->and($lines)->toContain('Disallow: '.$path.'/')
            ->and($lines)->not->toContain('Disallow: '.$path);
    })->with([
        '/mizita-admin',
        '/calendar',
        '/statistics',
        '/payments',
        '/services',
        '/customers',
        '/integrations',
        '/settings',
        '/onboarding',
        '/password',
        '/team-access',
        '/login',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/two-factor-challenge',
        '/user',
        '/account',
        '/auth',
        '/api',
        '/*/book',
    ]);

    it('writes every disallow as an exact and nested pair', function () {
        $disallowed = array_values(array_filter(
            ($this->lines)(),
            static fn (string $line): bool => str_starts_with($line, 'Disallow: '),
        ));

        expect(count($disallowed) % 2)->toBe(0);

        foreach (array_chunk($disallowed, 2) as [$exact, $nested]) {
            expect($exact)->toEndWith('$')
                ->and($nested)->toBe(substr($exact, 0, -1).'/');
        }
    });

    it('leaves the public pages crawlable', function (string $path) {
        $lines = ($this->lines)();

        expect($lines)->not->toContain('Disallow: '.$path.'$')
            ->and($lines)->not->toContain('Disallow: '.$path.'/');
    })->with(['/terms', '/privacy', '/cookies', '/sitemap.xml', '/ada-salon', '/*']);

    it('closes with a blank line and the absolute sitemap url', function () {
        expect(array_slice(($this->lines)(), -2))->toBe(['', 'Sitemap: '.ROBOTS_TXT_APP_URL.'/sitemap.xml']);
    });
});
