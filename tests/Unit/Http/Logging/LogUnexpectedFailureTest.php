<?php

declare(strict_types=1);

use App\Http\Logging\LogUnexpectedFailure;
use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Psr\Log\LoggerInterface;

const LOGGED_ID_TOKEN = 'eyJhbGciOiJSUzI1NiJ9.another-google-id-token-that-must-never-be-logged';

const LOGGED_ACCOUNT_EMAIL = 'grace@example.com';

function failingGoogleSignInRequest(): Request
{
    $request = Request::create(
        '/api/auth/google',
        'POST',
        [],
        ['session' => 'a-session-cookie'],
        [],
        ['HTTP_AUTHORIZATION' => 'Bearer '.LOGGED_ID_TOKEN, 'CONTENT_TYPE' => 'application/json'],
        json_encode(['id_token' => LOGGED_ID_TOKEN, 'email' => LOGGED_ACCOUNT_EMAIL]),
    );

    $route = new Route('POST', 'api/auth/google', []);
    $route->name('auth.google');

    $request->setRouteResolver(fn () => $route);

    return $request;
}

function accountThatFailedToSignIn(): User
{
    $account = new User;
    $account->uuid = '01930000-0000-7000-8000-00000000000a';
    $account->email = LOGGED_ACCOUNT_EMAIL;
    $account->name = 'Grace Hopper';

    return $account;
}

/**
 * @param  array<string, mixed>  $context
 */
function flattenLogged(array $context): string
{
    return json_encode(array_map(
        fn (mixed $value): mixed => $value instanceof Throwable
            ? [$value::class, $value->getMessage(), $value->getTraceAsString()]
            : $value,
        $context,
    ), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function applicationServing(Request $request): Application
{
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('runningInConsole')->andReturn(false);
    $app->shouldReceive('make')->with(Request::class)->andReturn($request);

    return $app;
}

function applicationRunningInConsole(): Application
{
    $app = Mockery::spy(Application::class);
    $app->shouldReceive('runningInConsole')->andReturn(true);

    return $app;
}

beforeEach(function () {
    $this->logger = Mockery::spy(LoggerInterface::class);
});

describe('logging a failure raised while serving a request', function () {
    beforeEach(function () {
        $this->request = failingGoogleSignInRequest();
        $this->request->setUserResolver(fn () => accountThatFailedToSignIn());

        $this->log = new LogUnexpectedFailure($this->logger, applicationServing($this->request));
    });

    it('writes one error entry describing the request that failed', function () {
        $error = new RuntimeException('Google rejected the credential.');

        ($this->log)($error);

        $this->logger->shouldHaveReceived('error')->once()->with('Google rejected the credential.', [
            'exception' => $error,
            'route' => 'auth.google',
            'method' => 'POST',
            'path' => 'api/auth/google',
            'user_id' => '01930000-0000-7000-8000-00000000000a',
        ]);
    });

    it('logs at the error level and at no other', function () {
        ($this->log)(new RuntimeException('boom'));

        $this->logger->shouldHaveReceived('error')->once();
        $this->logger->shouldNotHaveReceived('log');
        $this->logger->shouldNotHaveReceived('emergency');
        $this->logger->shouldNotHaveReceived('alert');
        $this->logger->shouldNotHaveReceived('critical');
        $this->logger->shouldNotHaveReceived('warning');
        $this->logger->shouldNotHaveReceived('notice');
        $this->logger->shouldNotHaveReceived('info');
        $this->logger->shouldNotHaveReceived('debug');
    });

    it('keeps the throwable itself so the handler can format the trace', function () {
        $error = new RuntimeException('boom');

        ($this->log)($error);

        $this->logger->shouldHaveReceived('error')->once()
            ->with(Mockery::any(), Mockery::on(fn (array $context): bool => $context['exception'] === $error));
    });
});

describe('logging a failure raised outside a request', function () {
    beforeEach(function () {
        $this->app = applicationRunningInConsole();
        $this->log = new LogUnexpectedFailure($this->logger, $this->app);
    });

    it('writes the throwable alone', function () {
        $error = new RuntimeException('The reminder job exploded.');

        ($this->log)($error);

        $this->logger->shouldHaveReceived('error')->once()->with('The reminder job exploded.', ['exception' => $error]);
    });

    it('omits every request descriptor rather than inventing one', function (string $descriptor) {
        ($this->log)(new RuntimeException('boom'));

        $this->logger->shouldHaveReceived('error')->once()
            ->with(Mockery::any(), Mockery::on(fn (array $context): bool => ! array_key_exists($descriptor, $context)));
    })->with(['route', 'method', 'path', 'user_id']);

    it('never asks the container for the synthetic console request', function () {
        ($this->log)(new RuntimeException('boom'));

        $this->app->shouldNotHaveReceived('make');
        $this->logger->shouldHaveReceived('error')->once();
    });
});

describe('keeping credentials out of the log', function () {
    it('never lets the bearer credential the request carried reach the log context', function () {
        $request = failingGoogleSignInRequest();
        $request->setUserResolver(fn () => accountThatFailedToSignIn());

        $log = new LogUnexpectedFailure($this->logger, applicationServing($request));

        ($log)(new RuntimeException('Google rejected the credential.'));

        $this->logger->shouldHaveReceived('error')->once()
            ->with(Mockery::any(), Mockery::on(
                fn (array $context): bool => ! str_contains(flattenLogged($context), LOGGED_ID_TOKEN),
            ));
    });

    it('never lets the account email reach the log context', function () {
        $request = failingGoogleSignInRequest();
        $request->setUserResolver(fn () => accountThatFailedToSignIn());

        $log = new LogUnexpectedFailure($this->logger, applicationServing($request));

        ($log)(new RuntimeException('boom'));

        $this->logger->shouldHaveReceived('error')->once()
            ->with(Mockery::any(), Mockery::on(
                fn (array $context): bool => ! str_contains(flattenLogged($context), LOGGED_ACCOUNT_EMAIL)
                    && $context['user_id'] === '01930000-0000-7000-8000-00000000000a',
            ));
    });

    it('carries the four request descriptors and nothing else', function () {
        $request = failingGoogleSignInRequest();
        $request->setUserResolver(fn () => accountThatFailedToSignIn());

        $log = new LogUnexpectedFailure($this->logger, applicationServing($request));

        ($log)(new RuntimeException('boom'));

        $this->logger->shouldHaveReceived('error')->once()
            ->with(Mockery::any(), Mockery::on(
                fn (array $context): bool => array_keys($context) === ['exception', 'route', 'method', 'path', 'user_id'],
            ));
    });

    it('leaves a secret carried by the throwable message out of every request descriptor', function () {
        $request = failingGoogleSignInRequest();
        $request->setUserResolver(fn () => accountThatFailedToSignIn());

        $log = new LogUnexpectedFailure($this->logger, applicationServing($request));

        ($log)(new RuntimeException('Rejected id_token '.LOGGED_ID_TOKEN));

        $this->logger->shouldHaveReceived('error')->once()
            ->with(Mockery::any(), Mockery::on(function (array $context): bool {
                unset($context['exception']);

                return ! str_contains(flattenLogged($context), LOGGED_ID_TOKEN);
            }));
    });
});
