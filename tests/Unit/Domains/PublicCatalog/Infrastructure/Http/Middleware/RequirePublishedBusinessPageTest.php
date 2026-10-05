<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\UseCases\VerifyBusinessPage;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Infrastructure\Http\Middleware\RequirePublishedBusinessPage;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);
    $this->middleware = new RequirePublishedBusinessPage(new VerifyBusinessPage($this->businesses));

    $this->ranTheRestOfTheStack = false;
    $this->bookingStep = new Response('the service step', Response::HTTP_OK);
    $this->next = function (): Response {
        $this->ranTheRestOfTheStack = true;

        return $this->bookingStep;
    };

    $this->requestFor = static function (?string $slug): Request {
        $request = Request::create('/'.($slug ?? '').'/book');
        $route = (new Route('GET', '/{slug}/book', static fn () => null))->bind($request);

        if ($slug !== null) {
            $route->setParameter('slug', $slug);
        }

        $request->setRouteResolver(static fn (): Route => $route);

        return $request;
    };

    $this->refusal = function (?string $slug): ?NotFoundHttpException {
        try {
            $this->middleware->handle(($this->requestFor)($slug), $this->next);
        } catch (NotFoundHttpException $notFound) {
            return $notFound;
        }

        return null;
    };
});

describe('a booking flow for a published business', function () {
    it('lets the visitor through to the step they asked for', function () {
        $this->businesses->shouldReceive('existsBySlug')->once()->with(PublicCatalogFixtures::SLUG)->andReturnTrue();

        $response = $this->middleware->handle(($this->requestFor)(PublicCatalogFixtures::SLUG), $this->next);

        expect($response)->toBe($this->bookingStep)
            ->and($this->ranTheRestOfTheStack)->toBeTrue();
    });
});

describe('a booking flow for a business that is not there', function () {
    it('answers 404 for a slug no published business answers to', function () {
        $this->businesses->shouldReceive('existsBySlug')->once()->with(PublicCatalogFixtures::UNKNOWN_SLUG)->andReturnFalse();

        expect(($this->refusal)(PublicCatalogFixtures::UNKNOWN_SLUG)?->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    });

    it('answers 404 without asking anything for a slug no business could own', function (?string $slug) {
        $this->businesses->shouldNotReceive('existsBySlug');

        expect(($this->refusal)($slug)?->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    })->with([
        'no slug parameter at all' => null,
        'uppercase' => 'Ada-Salon',
        'unicode' => 'peluquería',
    ]);
});

describe('where the guard stands', function () {
    it('guards every step of the booking flow', function (string $routeName) {
        $route = $this->app->make('router')->getRoutes()->getByName($routeName);

        expect($route?->gatherMiddleware())->toContain(RequirePublishedBusinessPage::class);
    })->with([
        'booking-flow.service',
        'booking-flow.staff',
        'booking-flow.time',
        'booking-flow.details',
    ]);
});
