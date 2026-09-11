<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

/*
| The locale props are what i18next boots from, so the first paint is already in
| the negotiated language. SetLocale is prepended to the web group and
| HandleInertiaRequests appended to it, which is the ordering these assertions
| depend on: share() reads app()->getLocale() after SetLocale has run.
|
| No database is touched here.
*/

beforeEach(function () {
    // Symfony's Request::create() synthesises "Accept-Language: en-us,en;q=0.5" on
    // every test request; blanking it keeps the default-locale case honest.
    $this->withHeader('Accept-Language', '');
});

it('shares the negotiated locale and the supported list', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/welcome')
            ->where('locale', 'es')
            ->where('supportedLocales', ['es', 'en'])
        );
});

it('shares the locale the request negotiated, not the application default', function (array $query, array $headers) {
    $uri = '/'.($query === [] ? '' : '?'.http_build_query($query));

    $this->get($uri, $headers)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'en'));
})->with([
    '?lang=' => [['lang' => 'en'], []],
    'X-Locale' => [[], ['X-Locale' => 'en']],
    'Accept-Language' => [[], ['Accept-Language' => 'en-GB,en;q=0.9']],
]);

it('shares the supported list straight from config, so the frontend mirrors one source', function () {
    config(['localization.supported' => ['es', 'en', 'ca']]);

    $this->get('/')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('supportedLocales', ['es', 'en', 'ca']));
});

it('renders the root document in the negotiated language', function () {
    // app.blade.php sets <html lang> from app()->getLocale(); it is the first thing
    // a screen reader and a search engine read, and it is set before any JS runs.
    $this->get('/?lang=en')->assertSee('<html lang="en"', escape: false);
    $this->get('/')->assertSee('<html lang="es"', escape: false);
});
