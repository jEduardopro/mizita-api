<?php

declare(strict_types=1);

namespace App\Http\Seo;

final class CanonicalUrls
{
    public const BUSINESS_PAGE_ROUTE = 'booking-page';

    public const SITEMAP_ROUTE = 'sitemap';

    public function forStaticPage(StaticPage $page): string
    {
        return $this->forRoute($page->routeName());
    }

    public function forBusinessPage(string $slug): string
    {
        return $this->forRoute(self::BUSINESS_PAGE_ROUTE, ['slug' => $slug]);
    }

    public function forSitemap(): string
    {
        return $this->forRoute(self::SITEMAP_ROUTE);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function forRoute(string $name, array $parameters = []): string
    {
        return rtrim((string) config('app.url'), '/').route($name, $parameters, absolute: false);
    }
}
