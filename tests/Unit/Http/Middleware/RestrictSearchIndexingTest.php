<?php

declare(strict_types=1);

use App\Http\Middleware\RestrictSearchIndexing;
use App\Http\Seo\SearchIndexing;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->middleware = new RestrictSearchIndexing(new SearchIndexing);
    $this->page = new Response('the landing page', Response::HTTP_OK);

    $this->handle = fn (?Response $page = null): Response => $this->middleware->handle(
        Request::create('/'),
        fn (): Response => $page ?? $this->page,
    );
});

describe('a site closed to search engines', function () {
    beforeEach(function () {
        config(['seo.indexable' => false]);
    });

    it('tells every crawler neither to index the page nor to follow its links', function () {
        expect(($this->handle)()->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
    });

    it('hands back the response the rest of the stack produced', function () {
        $response = ($this->handle)();

        expect($response)->toBe($this->page)
            ->and($response->getContent())->toBe('the landing page')
            ->and($response->getStatusCode())->toBe(Response::HTTP_OK);
    });

    it('overrides a weaker directive the page set for itself', function () {
        $notFound = new Response('', Response::HTTP_NOT_FOUND, ['X-Robots-Tag' => 'noindex']);

        expect(($this->handle)($notFound)->headers->all('x-robots-tag'))->toBe(['noindex, nofollow']);
    });

    it('closes off every status, not only a success', function (int $status) {
        expect(($this->handle)(new Response('', $status))->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
    })->with([Response::HTTP_FOUND, Response::HTTP_NOT_FOUND, Response::HTTP_INTERNAL_SERVER_ERROR]);
});

describe('a site open to search engines', function () {
    beforeEach(function () {
        config(['seo.indexable' => true]);
    });

    it('adds no robots header', function () {
        expect(($this->handle)()->headers->has('X-Robots-Tag'))->toBeFalse();
    });

    it('leaves the directive a page set for itself alone', function () {
        $notFound = new Response('', Response::HTTP_NOT_FOUND, ['X-Robots-Tag' => 'noindex']);

        expect(($this->handle)($notFound)->headers->get('X-Robots-Tag'))->toBe('noindex');
    });

    it('hands back the response the rest of the stack produced', function () {
        expect(($this->handle)())->toBe($this->page);
    });
});
