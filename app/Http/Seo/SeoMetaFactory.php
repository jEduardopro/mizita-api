<?php

declare(strict_types=1);

namespace App\Http\Seo;

use App\Domains\PublicCatalog\Application\Dtos\BusinessPageSharePreview;
use Illuminate\Support\Str;

final class SeoMetaFactory
{
    private const TITLE_SEPARATOR = ' · ';

    private const MAXIMUM_DESCRIPTION_LENGTH = 160;

    public function __construct(
        private readonly CanonicalUrls $urls,
        private readonly SearchIndexing $indexing,
    ) {}

    public function forStaticPage(StaticPage $page): SeoMeta
    {
        $title = $this->withAppName((string) __($page->titleKey()));

        return new SeoMeta(
            title: $title,
            shareTitle: $title,
            description: (string) __($page->descriptionKey(), ['app' => $this->appName()]),
            canonicalUrl: $this->urls->forStaticPage($page),
            robots: $this->indexing->directiveForIndexablePage(),
            imageUrl: null,
        );
    }

    public function forBusinessPage(string $slug, BusinessPageSharePreview $preview): SeoMeta
    {
        $shareTitle = $this->businessPageTitle($preview);

        return new SeoMeta(
            title: $this->withAppName($shareTitle),
            shareTitle: $shareTitle,
            description: $this->businessPageDescription($preview),
            canonicalUrl: $this->urls->forBusinessPage($slug),
            robots: $this->indexing->directiveForIndexablePage(),
            imageUrl: $preview->imageUrl,
        );
    }

    private function withAppName(string $title): string
    {
        return $title.self::TITLE_SEPARATOR.$this->appName();
    }

    private function appName(): string
    {
        return (string) config('app.name');
    }

    private function businessPageTitle(BusinessPageSharePreview $preview): string
    {
        if ($preview->city === null) {
            return (string) __('seo.business_page.title', ['name' => $preview->name]);
        }

        return (string) __('seo.business_page.title_with_city', ['name' => $preview->name, 'city' => $preview->city]);
    }

    private function businessPageDescription(BusinessPageSharePreview $preview): string
    {
        if (filled($preview->about)) {
            return Str::limit(Str::squish((string) $preview->about), self::MAXIMUM_DESCRIPTION_LENGTH);
        }

        return (string) __('seo.business_page.description', ['name' => $preview->name]);
    }
}
