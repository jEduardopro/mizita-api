<?php

declare(strict_types=1);

use App\Domains\Accounts\Exceptions\PasswordChangeRequired;
use App\Http\Middleware\RequireFreshPassword;
use App\Http\Middleware\SetBusinessContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeImpersonationStatus;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, string>  $headers
 */
function freshPasswordRequest(string $uri, ?User $user, array $headers = []): Request
{
    $request = Request::create($uri, 'GET');

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    $request->setUserResolver(static fn (): ?User => $user);

    return $request;
}

function accountAwaitingPasswordChange(): User
{
    return (new User)->setRawAttributes(['password' => '$2y$04$temporary.hash', 'must_change_password' => true]);
}

beforeEach(function () {
    $this->ranTheRestOfTheStack = false;
    $this->expected = new Response('the page', 200);
    $this->next = function () {
        $this->ranTheRestOfTheStack = true;

        return $this->expected;
    };
    $this->middleware = new RequireFreshPassword(new FakeImpersonationStatus);
});

describe('letting a caller through', function () {
    it('passes an account that chose its own password', function () {
        $account = (new User)->setRawAttributes(['password' => '$2y$04$chosen.hash', 'must_change_password' => false]);

        expect($this->middleware->handle(freshPasswordRequest('/api/services', $account), $this->next))
            ->toBe($this->expected);
    });

    it('passes an account on a row written before the flag existed', function () {
        $account = (new User)->setRawAttributes(['password' => '$2y$04$chosen.hash']);

        expect($this->middleware->handle(freshPasswordRequest('/api/services', $account), $this->next))
            ->toBe($this->expected);
    });

    it('leaves an unauthenticated request to the guards that own it', function () {
        expect($this->middleware->handle(freshPasswordRequest('/api/services', null), $this->next))
            ->toBe($this->expected);
    });
});

describe('an account still holding its temporary password', function () {
    it('refuses an api request with password_change_required', function () {
        expect(fn () => $this->middleware->handle(
            freshPasswordRequest('/api/services', accountAwaitingPasswordChange()),
            $this->next,
        ))->toThrow(PasswordChangeRequired::class);
    });

    it('refuses a web request that asks for json', function () {
        expect(fn () => $this->middleware->handle(
            freshPasswordRequest('/calendar', accountAwaitingPasswordChange(), ['Accept' => 'application/json']),
            $this->next,
        ))->toThrow(PasswordChangeRequired::class);
    });

    it('runs none of the rest of the stack when it refuses', function () {
        expect(fn () => $this->middleware->handle(freshPasswordRequest('/api/services', accountAwaitingPasswordChange()), $this->next))
            ->toThrow(PasswordChangeRequired::class)
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    });

    it('sends a page visit to the change password screen', function (array $headers) {
        $response = $this->middleware->handle(
            freshPasswordRequest('/calendar', accountAwaitingPasswordChange(), $headers),
            $this->next,
        );

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe(route('password.change'))
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    })->with([
        'a plain browser visit' => [[]],
        'an inertia visit' => [['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml']],
    ]);

    it('redirects an inertia visit even to an api path, since inertia cannot show a json refusal', function () {
        $response = $this->middleware->handle(
            freshPasswordRequest('/api/services', accountAwaitingPasswordChange(), ['X-Inertia' => 'true']),
            $this->next,
        );

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe(route('password.change'));
    });
});

describe('a platform admin impersonating the owner', function () {
    beforeEach(function () {
        $this->middleware = new RequireFreshPassword(new FakeImpersonationStatus([
            'business_name' => 'Barbería Ñandú',
            'owner_name' => 'Ada Lovelace',
            'expires_at' => '2026-09-25T16:00:00+00:00',
        ]));
    });

    it('lets a page visit through even though the owner still holds a temporary password', function (array $headers) {
        $response = $this->middleware->handle(
            freshPasswordRequest('/calendar', accountAwaitingPasswordChange(), $headers),
            $this->next,
        );

        expect($response)->toBe($this->expected)
            ->and($this->ranTheRestOfTheStack)->toBeTrue();
    })->with([
        'a plain browser visit' => [[]],
        'an inertia visit' => [['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml']],
    ]);

    it('lets an api request through instead of refusing it with password_change_required', function () {
        expect($this->middleware->handle(freshPasswordRequest('/api/services', accountAwaitingPasswordChange()), $this->next))
            ->toBe($this->expected);
    });

    it('still lets an account with its own password through', function () {
        $account = (new User)->setRawAttributes(['password' => '$2y$04$chosen.hash', 'must_change_password' => false]);

        expect($this->middleware->handle(freshPasswordRequest('/api/services', $account), $this->next))
            ->toBe($this->expected);
    });
});

describe('the business middleware group', function () {
    it('holds the caller to a fresh password before it resolves their business', function () {
        expect(app(Router::class)->getMiddlewareGroups()['business'] ?? null)->toBe([
            RequireFreshPassword::class,
            SetBusinessContext::class,
        ]);
    });
});
