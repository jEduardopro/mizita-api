<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\BusinessPageSharePreview;
use App\Http\Seo\CanonicalUrls;
use App\Http\Seo\RobotsDirective;
use App\Http\Seo\SearchIndexing;
use App\Http\Seo\SeoMeta;
use App\Http\Seo\SeoMetaFactory;
use App\Http\Seo\StaticPage;
use Tests\Support\Addresses\AddressFixtures;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\TestCase;

uses(TestCase::class);

const SEO_META_APP_URL = 'https://mizita.test';

const SEO_META_DESCRIPTION_LIMIT = 160;

beforeEach(function () {
    config(['app.name' => 'Mizita', 'app.url' => SEO_META_APP_URL, 'seo.indexable' => true]);
    $this->app->setLocale('es');

    $this->factory = new SeoMetaFactory(new CanonicalUrls, new SearchIndexing);

    $this->preview = static fn (
        ?string $city = AddressFixtures::CITY,
        ?string $about = 'Cortes y color desde 2019.',
        ?string $imageUrl = PublicCatalogFixtures::BANNER_URL,
    ): BusinessPageSharePreview => new BusinessPageSharePreview(PublicCatalogFixtures::NAME, $city, $about, $imageUrl);

    $this->forBusinessPage = fn (BusinessPageSharePreview $preview): SeoMeta => $this->factory
        ->forBusinessPage(PublicCatalogFixtures::SLUG, $preview);
});

describe('a static page', function () {
    it('titles the page after its own heading followed by the app name', function (string $locale, StaticPage $page, string $title) {
        $this->app->setLocale($locale);

        $meta = $this->factory->forStaticPage($page);

        expect($meta->title)->toBe($title.' · Mizita')
            ->and($meta->shareTitle)->toBe($title.' · Mizita');
    })->with([
        'es landing' => ['es', StaticPage::Landing, 'Agenda de citas para equipos pequeños'],
        'es terms' => ['es', StaticPage::Terms, 'Términos y Condiciones'],
        'es privacy' => ['es', StaticPage::Privacy, 'Aviso de Privacidad'],
        'es cookies' => ['es', StaticPage::Cookies, 'Política de Cookies'],
        'en landing' => ['en', StaticPage::Landing, 'Appointment scheduling for small teams'],
        'en terms' => ['en', StaticPage::Terms, 'Terms and Conditions'],
        'en privacy' => ['en', StaticPage::Privacy, 'Privacy Notice'],
        'en cookies' => ['en', StaticPage::Cookies, 'Cookie Policy'],
    ]);

    it('describes the page in the visitor language', function (string $locale, string $description) {
        $this->app->setLocale($locale);

        expect($this->factory->forStaticPage(StaticPage::Cookies)->description)->toBe($description);
    })->with([
        'es' => ['es', 'Qué cookies utiliza Mizita, para qué sirven y cómo puedes gestionarlas.'],
        'en' => ['en', 'Which cookies Mizita uses, what they are for and how you can manage them.'],
    ]);

    it('names the app in every description instead of leaving a placeholder', function (string $locale, StaticPage $page) {
        $this->app->setLocale($locale);

        expect($this->factory->forStaticPage($page)->description)
            ->toContain('Mizita')
            ->not->toContain(':app')
            ->not->toStartWith('seo.');
    })->with(['es', 'en'])->with(StaticPage::cases());

    it('points the canonical url at the page under the application url', function () {
        expect($this->factory->forStaticPage(StaticPage::Privacy)->canonicalUrl)->toBe(SEO_META_APP_URL.'/privacy');
    });

    it('offers no share image', function () {
        expect($this->factory->forStaticPage(StaticPage::Landing)->imageUrl)->toBeNull();
    });

    it('lets the page be indexed when the site is open to search engines', function () {
        expect($this->factory->forStaticPage(StaticPage::Terms)->robots)->toBe(RobotsDirective::Index);
    });

    it('keeps the page out of the index when the site is closed to search engines', function () {
        config(['seo.indexable' => false]);

        expect($this->factory->forStaticPage(StaticPage::Terms)->robots)->toBe(RobotsDirective::NoIndex);
    });
});

describe('the title of a business page', function () {
    it('names the business and its city, then asks to book, then the app', function (string $locale, string $callToAction) {
        $this->app->setLocale($locale);

        $meta = ($this->forBusinessPage)(($this->preview)());

        expect($meta->title)->toBe('Ada Salón | Ciudad de México · '.$callToAction.' · Mizita');
    })->with([
        'es' => ['es', 'Reserva ahora'],
        'en' => ['en', 'Book now'],
    ]);

    it('leaves the city out for a business that filed no address', function (string $locale, string $callToAction) {
        $this->app->setLocale($locale);

        $meta = ($this->forBusinessPage)(($this->preview)(city: null));

        expect($meta->title)->toBe('Ada Salón · '.$callToAction.' · Mizita')
            ->and($meta->title)->not->toContain('|');
    })->with([
        'es' => ['es', 'Reserva ahora'],
        'en' => ['en', 'Book now'],
    ]);

    it('shares the title without the app name, since the unfurl already shows the site', function () {
        expect(($this->forBusinessPage)(($this->preview)())->shareTitle)->toBe('Ada Salón | Ciudad de México · Reserva ahora')
            ->and(($this->forBusinessPage)(($this->preview)(city: null))->shareTitle)->toBe('Ada Salón · Reserva ahora');
    });

    it('prints a business name that looks like a placeholder literally', function () {
        $preview = new BusinessPageSharePreview('Spa :city', 'Monterrey', null, null);

        expect(($this->forBusinessPage)($preview)->title)->toBe('Spa :city | Monterrey · Reserva ahora · Mizita');
    });
});

describe('the description of a business page', function () {
    it('uses what the business wrote about itself, collapsed onto one line', function () {
        $meta = ($this->forBusinessPage)(($this->preview)(about: "  Cortes y color\n\n  desde   2019.  "));

        expect($meta->description)->toBe('Cortes y color desde 2019.');
    });

    it('keeps an about of exactly the limit whole', function (string $character) {
        $about = str_repeat($character, SEO_META_DESCRIPTION_LIMIT);

        expect(($this->forBusinessPage)(($this->preview)(about: $about))->description)->toBe($about);
    })->with(['ascii' => 'a', 'accented' => 'ñ']);

    it('cuts an about one character past the limit and marks the cut', function (string $character) {
        $about = str_repeat($character, SEO_META_DESCRIPTION_LIMIT + 1);

        expect(($this->forBusinessPage)(($this->preview)(about: $about))->description)
            ->toBe(str_repeat($character, SEO_META_DESCRIPTION_LIMIT).'...');
    })->with(['ascii' => 'a', 'accented' => 'ñ']);

    it('counts the limit after collapsing the whitespace', function () {
        $about = implode("\n\n", str_split(str_repeat('a', SEO_META_DESCRIPTION_LIMIT), 40));

        expect(mb_strlen(($this->forBusinessPage)(($this->preview)(about: $about))->description))
            ->toBe(SEO_META_DESCRIPTION_LIMIT + 3)
            ->and(($this->forBusinessPage)(($this->preview)(about: $about))->description)->not->toContain("\n");
    });

    it('falls back to an invitation to book when the business wrote nothing', function (?string $about, string $locale, string $description) {
        $this->app->setLocale($locale);

        expect(($this->forBusinessPage)(($this->preview)(about: $about))->description)->toBe($description);
    })->with([
        'null' => [null],
        'empty' => [''],
        'spaces' => ['   '],
        'a newline and a tab' => ["\n\t"],
    ])->with([
        'es' => ['es', 'Reserva en línea con Ada Salón.'],
        'en' => ['en', 'Book online with Ada Salón.'],
    ]);
});

describe('the rest of a business page meta', function () {
    it('points the canonical url at the slug under the application url', function () {
        expect(($this->forBusinessPage)(($this->preview)())->canonicalUrl)->toBe(SEO_META_APP_URL.'/'.PublicCatalogFixtures::SLUG);
    });

    it('shares the image the preview chose', function () {
        expect(($this->forBusinessPage)(($this->preview)())->imageUrl)->toBe(PublicCatalogFixtures::BANNER_URL);
    });

    it('shares no image for a business that uploaded none', function () {
        expect(($this->forBusinessPage)(($this->preview)(imageUrl: null))->imageUrl)->toBeNull();
    });

    it('follows the site indexing switch', function (bool $indexable, RobotsDirective $directive) {
        config(['seo.indexable' => $indexable]);

        expect(($this->forBusinessPage)(($this->preview)())->robots)->toBe($directive);
    })->with([
        'open' => [true, RobotsDirective::Index],
        'closed' => [false, RobotsDirective::NoIndex],
    ]);
});
