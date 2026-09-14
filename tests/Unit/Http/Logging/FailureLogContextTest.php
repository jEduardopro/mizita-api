<?php

declare(strict_types=1);

use App\Http\Logging\FailureLogContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

const ID_TOKEN = 'eyJhbGciOiJSUzI1NiJ9.a-google-id-token-that-must-never-be-logged';

const ACCOUNT_EMAIL = 'ada@example.com';

function googleSignInRequest(): Request
{
    $request = Request::create(
        '/api/auth/google',
        'POST',
        [],
        ['session' => 'a-session-cookie'],
        [],
        ['HTTP_AUTHORIZATION' => 'Bearer '.ID_TOKEN, 'CONTENT_TYPE' => 'application/json'],
        json_encode(['id_token' => ID_TOKEN, 'email' => ACCOUNT_EMAIL]),
    );

    $route = new Route('POST', 'api/auth/google', []);
    $route->name('auth.google');

    $request->setRouteResolver(fn () => $route);

    return $request;
}

function signedInAccount(): User
{
    $account = new User;
    $account->uuid = '01930000-0000-7000-8000-000000000001';
    $account->email = ACCOUNT_EMAIL;
    $account->name = 'Ada Lovelace';

    return $account;
}

/**
 * @param  array<string, mixed>  $context
 */
function flatten(array $context): string
{
    return json_encode(array_map(
        fn (mixed $value): mixed => $value instanceof Throwable
            ? [$value::class, $value->getMessage(), $value->getTraceAsString()]
            : $value,
        $context,
    ), JSON_THROW_ON_ERROR);
}

it('describes the request that failed', function () {
    $request = googleSignInRequest();
    $request->setUserResolver(fn () => signedInAccount());

    $error = new RuntimeException('Google rejected the credential.');

    expect(FailureLogContext::for($request, $error))->toBe([
        'exception' => $error,
        'route' => 'auth.google',
        'method' => 'POST',
        'path' => 'api/auth/google',
        'user_id' => '01930000-0000-7000-8000-000000000001',
    ]);
});

it('carries exactly five keys and no more', function () {
    $context = FailureLogContext::for(googleSignInRequest(), new RuntimeException('boom'));

    expect(array_keys($context))->toBe(['exception', 'route', 'method', 'path', 'user_id']);
});

it('never carries the request body, input, headers or cookies', function (string $forbidden) {
    $context = FailureLogContext::for(googleSignInRequest(), new RuntimeException('boom'));

    expect($context)->not->toHaveKey($forbidden);
})->with([
    'body', 'input', 'request', 'payload', 'parameters', 'headers', 'cookies', 'query', 'user', 'email', 'token',
]);

it('never lets a bearer credential from the body reach the log context', function () {
    $request = googleSignInRequest();
    $request->setUserResolver(fn () => signedInAccount());

    $context = FailureLogContext::for($request, new RuntimeException('Google rejected the credential.'));

    expect(flatten($context))->not->toContain(ID_TOKEN);
});

it('never lets the account email reach the log context', function () {
    $request = googleSignInRequest();
    $request->setUserResolver(fn () => signedInAccount());

    $context = FailureLogContext::for($request, new RuntimeException('boom'));

    expect(flatten($context))->not->toContain(ACCOUNT_EMAIL)
        ->and($context['user_id'])->toBe('01930000-0000-7000-8000-000000000001');
});

it('identifies the account by its uuid, never by its integer key', function () {
    $account = signedInAccount();
    $account->id = 42;

    $request = googleSignInRequest();
    $request->setUserResolver(fn () => $account);

    expect(FailureLogContext::for($request, new RuntimeException('boom'))['user_id'])
        ->toBe('01930000-0000-7000-8000-000000000001');
});

it('reports no user for an anonymous caller', function () {
    expect(FailureLogContext::for(googleSignInRequest(), new RuntimeException('boom'))['user_id'])
        ->toBeNull();
});

it('reports no route for a request that matched none', function () {
    $request = Request::create('/api/auth/google', 'POST');

    expect(FailureLogContext::for($request, new RuntimeException('boom'))['route'])
        ->toBeNull();
});

it('reports no route for an unnamed route', function () {
    $request = Request::create('/api/auth/google', 'POST');
    $request->setRouteResolver(fn () => new Route('POST', 'api/auth/google', []));

    expect(FailureLogContext::for($request, new RuntimeException('boom'))['route'])
        ->toBeNull();
});

it('keeps the exception itself so the handler can format the trace', function () {
    $error = new RuntimeException('Google rejected the credential.');

    expect(FailureLogContext::for(googleSignInRequest(), $error)['exception'])->toBe($error);
});

describe('describing a failure raised outside a request', function () {
    it('carries the exception and nothing else', function () {
        $error = new RuntimeException('The reminder job exploded.');

        expect(FailureLogContext::withoutRequest($error))->toBe(['exception' => $error]);
    });

    it('omits every request descriptor rather than describing the synthetic console request', function (string $descriptor) {
        expect(FailureLogContext::withoutRequest(new RuntimeException('boom')))->not->toHaveKey($descriptor);
    })->with(['route', 'method', 'path', 'user_id']);

    it('carries exactly one key, so nothing from a previous request can ride along', function () {
        expect(array_keys(FailureLogContext::withoutRequest(new RuntimeException('boom'))))->toBe(['exception']);
    });

    it('keeps the exception itself so the handler can format the trace', function () {
        $error = new RuntimeException('boom');

        expect(FailureLogContext::withoutRequest($error)['exception'])->toBe($error);
    });
});
