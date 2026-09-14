<?php

declare(strict_types=1);

use App\Http\Exceptions\RenderDomainFailure;
use App\Http\Responses\FailurePayload;
use App\Http\Responses\JsonFailureRendering;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    app()->setLocale('en');

    $this->render = new RenderDomainFailure;
});

function renderableFailure(string $code, DomainFailureKind $kind): DomainFailure
{
    return new class($code, $kind) extends RuntimeException implements DomainFailure
    {
        public function __construct(
            private readonly string $errorCode,
            private readonly DomainFailureKind $failureKind,
        ) {
            parent::__construct("Developer detail about [{$errorCode}].");
        }

        public function errorCode(): string
        {
            return $this->errorCode;
        }

        public function kind(): DomainFailureKind
        {
            return $this->failureKind;
        }
    };
}

function renderFailureRequest(string $path, array $headers = []): Request
{
    $request = Request::create($path, 'POST');

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

function renderedBody(JsonResponse $response): array
{
    return json_decode($response->getContent(), associative: true);
}

describe('rendering a domain failure to the api', function () {
    it('answers with the status the kind maps to', function (DomainFailureKind $kind, int $status) {
        $response = ($this->render)(renderableFailure('no_business', $kind), renderFailureRequest('/api/businesses'));

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe($status);
    })->with([
        'invalid' => [DomainFailureKind::Invalid, 422],
        'conflict' => [DomainFailureKind::Conflict, 409],
        'not found' => [DomainFailureKind::NotFound, 404],
        'unauthenticated' => [DomainFailureKind::Unauthenticated, 401],
        'forbidden' => [DomainFailureKind::Forbidden, 403],
    ]);

    it('leaves no kind of the enum unrendered', function () {
        foreach (DomainFailureKind::cases() as $kind) {
            $response = ($this->render)(renderableFailure('no_business', $kind), renderFailureRequest('/api/businesses'));

            expect($response?->getStatusCode())->toBeGreaterThanOrEqual(400);
        }
    });

    it('writes a body of exactly the message and the code', function () {
        $response = ($this->render)(
            renderableFailure('no_business', DomainFailureKind::Forbidden),
            renderFailureRequest('/api/businesses'),
        );

        expect(renderedBody($response))->toBe([
            'message' => 'This user does not belong to a business.',
            'code' => 'no_business',
        ]);
    });

    it('renders exactly what the failure payload produces for the same code and kind', function () {
        $payload = FailurePayload::for('business_not_accessible', DomainFailureKind::Forbidden);

        $response = ($this->render)(
            renderableFailure('business_not_accessible', DomainFailureKind::Forbidden),
            renderFailureRequest('/api/businesses'),
        );

        expect(renderedBody($response))->toBe($payload->body)
            ->and($response->getStatusCode())->toBe($payload->status);
    });

    it('sends the translated message and never the developer exception message', function () {
        $response = ($this->render)(
            renderableFailure('no_business', DomainFailureKind::Forbidden),
            renderFailureRequest('/api/businesses'),
        );

        expect(renderedBody($response)['message'])->not->toContain('Developer detail');
    });

    it('speaks the locale the request resolved', function () {
        app()->setLocale('es');

        $response = ($this->render)(
            renderableFailure('no_business', DomainFailureKind::Forbidden),
            renderFailureRequest('/api/businesses'),
        );

        expect(renderedBody($response)['message'])->toBe('Este usuario no pertenece a ningún negocio.');
    });

    it('translates an unknown code into something, never into a raw key', function () {
        $response = ($this->render)(
            renderableFailure('no_business', DomainFailureKind::Invalid),
            renderFailureRequest('/api/businesses'),
        );

        expect(renderedBody($response)['message'])->not->toStartWith('messages.errors.');
    });
});

describe('deciding whether to render at all', function () {
    it('renders json for the request shapes the api owns, and hands the rest back to laravel', function (
        string $path,
        array $headers,
        bool $rendersJson,
    ) {
        $response = ($this->render)(
            renderableFailure('no_business', DomainFailureKind::Forbidden),
            renderFailureRequest($path, $headers),
        );

        expect($response instanceof JsonResponse)->toBe($rendersJson);
    })->with([
        'an api path' => ['/api/businesses', [], true],
        'a nested api path' => ['/api/businesses/name-availability', [], true],
        'a web path that asks for json' => ['/onboarding', ['Accept' => 'application/json'], true],
        'a web path that asks for json with a charset' => ['/onboarding', ['Accept' => 'application/json, text/plain, */*'], true],
        'a plain web request' => ['/onboarding', [], false],
        'a web request asking for html' => ['/onboarding', ['Accept' => 'text/html'], false],
        'a path merely containing api' => ['/rapid', [], false],
    ]);

    it('hands an inertia visit back to laravel, so a web controller answers it with a redirect', function (string $path, array $headers) {
        $response = ($this->render)(
            renderableFailure('no_business', DomainFailureKind::Forbidden),
            renderFailureRequest($path, $headers + ['X-Inertia' => 'true']),
        );

        expect($response)->toBeNull();
    })->with([
        'an inertia visit to a web path' => ['/onboarding', ['Accept' => 'application/json']],
        'an inertia visit to an api path' => ['/api/businesses', []],
        'an inertia visit with no accept header' => ['/onboarding', []],
    ]);

    it('lets bootstrap decide with the shared predicate and no copy of its own', function (string $path, array $headers) {
        $handler = app(ExceptionHandler::class);
        $callback = (new ReflectionClass($handler))
            ->getProperty('shouldRenderJsonWhenCallback')
            ->getValue($handler);

        expect($callback)->not->toBeNull();

        $request = renderFailureRequest($path, $headers);

        expect($callback($request, renderableFailure('no_business', DomainFailureKind::Forbidden)))
            ->toBe(JsonFailureRendering::appliesTo($request));
    })->with([
        'an api path' => ['/api/businesses', []],
        'a nested api path' => ['/api/businesses/name-availability', []],
        'a web path that asks for json' => ['/onboarding', ['Accept' => 'application/json']],
        'a web path that asks for json in a quality list' => ['/onboarding', ['Accept' => 'application/json, text/plain, */*']],
        'a plain web request' => ['/onboarding', []],
        'a web request asking for html' => ['/onboarding', ['Accept' => 'text/html']],
        'a path merely containing api' => ['/rapid', []],
        'an inertia visit to an api path' => ['/api/businesses', ['X-Inertia' => 'true']],
        'an inertia visit to a web path' => ['/onboarding', ['X-Inertia' => 'true', 'Accept' => 'application/json']],
    ]);
});
