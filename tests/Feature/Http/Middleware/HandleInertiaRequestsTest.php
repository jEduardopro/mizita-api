<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withHeader('Accept-Language', '');
});

it('shares the resolved locale and the supported list', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/welcome')
            ->where('locale', 'es')
            ->where('supportedLocales', ['es', 'en'])
        );
});

it('shares the locale the request chose, not the application default', function (array $query, array $headers) {
    $uri = '/'.($query === [] ? '' : '?'.http_build_query($query));

    $this->get($uri, $headers)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'en'));
})->with([
    '?lang=' => [['lang' => 'en'], []],
    'X-Locale' => [[], ['X-Locale' => 'en']],
]);

it('boots the page in spanish for an english browser', function () {
    $this->get('/', ['Accept-Language' => 'en-US,en;q=0.9'])
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'es'));
});

it('shares the supported list straight from config, so the frontend mirrors one source', function () {
    config(['localization.supported' => ['es', 'en', 'ca']]);

    $this->get('/')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('supportedLocales', ['es', 'en', 'ca']));
});

it('renders the root document in the resolved language', function () {
    $this->get('/?lang=en')->assertSee('<html lang="en"', escape: false);
    $this->get('/')->assertSee('<html lang="es"', escape: false);
});
