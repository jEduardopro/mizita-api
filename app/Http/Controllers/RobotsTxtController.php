<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Seo\CanonicalUrls;
use App\Http\Seo\SearchIndexing;
use Illuminate\Http\Response;

final class RobotsTxtController extends Controller
{
    private const CONTENT_TYPE = 'text/plain; charset=UTF-8';

    private const EVERY_CRAWLER = 'User-agent: *';

    private const EVERYTHING = '/';

    private const EXACT_PATH_ANCHOR = '$';

    private const NESTED_PATH_PREFIX = '/';

    /**
     * @var list<string>
     */
    private const PRIVATE_PATHS = [
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
    ];

    public function __invoke(SearchIndexing $indexing, CanonicalUrls $urls): Response
    {
        $lines = $indexing->permitted()
            ? $this->indexableSite($urls)
            : $this->closedSite();

        return response(implode("\n", $lines)."\n", Response::HTTP_OK, ['Content-Type' => self::CONTENT_TYPE]);
    }

    /**
     * @return list<string>
     */
    private function indexableSite(CanonicalUrls $urls): array
    {
        return [
            self::EVERY_CRAWLER,
            'Allow: '.self::EVERYTHING,
            ...$this->privatePathRules(),
            '',
            'Sitemap: '.$urls->forSitemap(),
        ];
    }

    /**
     * @return list<string>
     */
    private function closedSite(): array
    {
        return [
            self::EVERY_CRAWLER,
            'Disallow: '.self::EVERYTHING,
        ];
    }

    /**
     * @return list<string>
     */
    private function privatePathRules(): array
    {
        $rules = [];

        foreach (self::PRIVATE_PATHS as $path) {
            $rules[] = 'Disallow: '.$path.self::EXACT_PATH_ANCHOR;
            $rules[] = 'Disallow: '.$path.self::NESTED_PATH_PREFIX;
        }

        return $rules;
    }
}
