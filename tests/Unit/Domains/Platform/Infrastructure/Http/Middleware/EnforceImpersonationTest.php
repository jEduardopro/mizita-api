<?php

declare(strict_types=1);

use App\Domains\Platform\Exceptions\ImpersonationEnded;
use App\Domains\Platform\Exceptions\PlatformSessionExpired;
use App\Domains\Platform\Infrastructure\Auth\PlatformActivity;
use App\Domains\Platform\Infrastructure\Http\PlatformRoutes;
use App\Http\Exceptions\PermissionDenied;
use App\Http\Responses\FailurePayload;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeClock;
use Tests\Support\Platform\ImpersonationFixtures;
use Tests\Support\Platform\PlatformSessionHarness;
use Tests\TestCase;

uses(TestCase::class);

const ENFORCE_TEN_MINUTES_IN = '2026-09-25T15:10:00+00:00';

const ENFORCE_TEN_MINUTES_BEFORE_EXPIRY = '2026-09-25T15:50:00+00:00';

const ENFORCE_INERTIA_HEADERS = ['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'];

/**
 * @param  array<string, string>  $headers
 */
function impersonatingOwner(string $uri, string $method = 'GET', array $headers = []): PlatformSessionHarness
{
    return (new PlatformSessionHarness($uri, $method, $headers))
        ->signInAdmin()
        ->signInOwner()
        ->impersonating(ImpersonationFixtures::begun())
        ->lastActiveAt(ImpersonationFixtures::now());
}

beforeEach(function () {
    $this->clock = new FakeClock(new DateTimeImmutable(ENFORCE_TEN_MINUTES_IN));
    $this->ranTheRestOfTheStack = false;
    $this->expected = new Response('the page', 200);
    $this->next = function () {
        $this->ranTheRestOfTheStack = true;

        return $this->expected;
    };
});

describe('with no impersonation in the session', function () {
    it('lets every request through, a sensitive one included', function () {
        $harness = (new PlatformSessionHarness('/user/password', 'PUT'))->signInOwner();

        expect($harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next))->toBe($this->expected);
    });

    it('records no admin activity for an ordinary owner session', function () {
        $harness = (new PlatformSessionHarness('/calendar'))->signInOwner();

        $harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next);

        expect($harness->session->has(PlatformActivity::SESSION_KEY))->toBeFalse();
    });
});

describe('a valid impersonation', function () {
    it('lets the admin use the dashboard as the owner', function (string $method, string $uri) {
        $harness = impersonatingOwner($uri, $method);

        expect($harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next))->toBe($this->expected)
            ->and($this->ranTheRestOfTheStack)->toBeTrue();
    })->with([
        'the calendar page' => ['GET', '/calendar'],
        'booking an appointment' => ['POST', '/api/appointments'],
        'reading the subscription' => ['GET', '/api/subscription'],
    ]);

    it('counts the request as admin activity, so the admin idle timeout keeps running', function () {
        $harness = impersonatingOwner('/calendar');

        $harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next);

        expect($harness->session->get(PlatformActivity::SESSION_KEY))->toBe($this->clock->now()->getTimestamp());
    });

    it('keeps the impersonation and both sign ins in place', function () {
        $harness = impersonatingOwner('/calendar');

        $harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next);

        expect($harness->holdsImpersonation())->toBeTrue()
            ->and($harness->admins->check())->toBeTrue()
            ->and($harness->owners->check())->toBeTrue()
            ->and($harness->businessSelection->forgotten)->toBe([]);
    });

    it('still holds one second before the hour runs out', function () {
        $this->clock = new FakeClock(new DateTimeImmutable('2026-09-25T15:59:59+00:00'));
        $harness = impersonatingOwner('/calendar')->lastActiveAt(new DateTimeImmutable(ENFORCE_TEN_MINUTES_BEFORE_EXPIRY));

        expect($harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next))->toBe($this->expected);
    });
});

describe('a sensitive request while impersonating', function () {
    it('refuses an api request with the permission failure the edge renders as 403', function (string $method, string $uri) {
        $harness = impersonatingOwner($uri, $method);

        expect(fn () => $harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next))
            ->toThrow(PermissionDenied::class)
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    })->with([
        'deleting the account' => ['DELETE', '/api/me/account'],
        'starting a checkout' => ['POST', '/api/subscription/checkout'],
        'revealing a temporary password' => ['GET', '/api/staff-members/'.ImpersonationFixtures::ACCOUNT_ID.'/temporary-password'],
    ]);

    it('sends an inertia visit back with the refusal flashed', function () {
        $harness = impersonatingOwner('/user/password', 'PUT', ENFORCE_INERTIA_HEADERS);

        $response = $harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next);

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getSession()?->get('error'))->toBe(FailurePayload::messageFor('missing_permission'))
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    });

    it('keeps the impersonation going after refusing', function () {
        $harness = impersonatingOwner('/api/me/account', 'DELETE');

        try {
            $harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next);
        } catch (PermissionDenied) {
        }

        expect($harness->holdsImpersonation())->toBeTrue()
            ->and($harness->owners->check())->toBeTrue();
    });
});

describe('the owner logout while impersonating', function () {
    beforeEach(function () {
        $this->harness = impersonatingOwner('/logout', 'POST', ENFORCE_INERTIA_HEADERS)->onRoute('logout');
        $this->response = $this->harness->enforceImpersonation($this->clock)->handle($this->harness->request, $this->next);
    });

    it('returns the admin to the business list instead of logging the owner out', function () {
        expect($this->response)->toBeInstanceOf(RedirectResponse::class)
            ->and($this->response->getTargetUrl())->toBe(route(PlatformRoutes::BUSINESSES))
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    });

    it('ends the impersonation and signs the owner out of this device only', function () {
        expect($this->harness->holdsImpersonation())->toBeFalse()
            ->and($this->harness->owners->check())->toBeFalse()
            ->and($this->harness->businessSelection->forgotten)->toBe([ImpersonationFixtures::ACCOUNT_ID]);
    });

    it('keeps the admin signed in', function () {
        expect($this->harness->admins->check())->toBeTrue();
    });
});

describe('an impersonation that no longer holds', function () {
    dataset('broken impersonations', [
        'the hour ran out' => [fn () => impersonatingOwner(...func_get_args())->lastActiveAt(new DateTimeImmutable(ENFORCE_TEN_MINUTES_BEFORE_EXPIRY)), ImpersonationFixtures::EXPIRES_AT],
        'another admin is signed in' => [fn () => impersonatingOwner(...func_get_args())->signInAdmin(ImpersonationFixtures::OTHER_ADMIN_ID), ENFORCE_TEN_MINUTES_IN],
        'the admin signed out' => [fn () => (new PlatformSessionHarness(...func_get_args()))->signInOwner()->impersonating(ImpersonationFixtures::begun())->lastActiveAt(ImpersonationFixtures::now()), ENFORCE_TEN_MINUTES_IN],
        'another owner is signed in' => [fn () => impersonatingOwner(...func_get_args())->signInOwner(ImpersonationFixtures::OTHER_ACCOUNT_ID), ENFORCE_TEN_MINUTES_IN],
        'the owner signed out' => [fn () => (new PlatformSessionHarness(...func_get_args()))->signInAdmin()->impersonating(ImpersonationFixtures::begun())->lastActiveAt(ImpersonationFixtures::now()), ENFORCE_TEN_MINUTES_IN],
        'the stored impersonation is unreadable' => [fn () => impersonatingOwner(...func_get_args())->holdingImpersonationPayload(['admin_uuid' => ImpersonationFixtures::ADMIN_ID]), ENFORCE_TEN_MINUTES_IN],
    ]);

    it('sends a page visit to the admin login', function (Closure $harnessFor, string $now) {
        $harness = $harnessFor('/calendar', 'GET', ENFORCE_INERTIA_HEADERS);

        $response = $harness->enforceImpersonation(new FakeClock(new DateTimeImmutable($now)))->handle($harness->request, $this->next);

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe(route(PlatformRoutes::LOGIN))
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    })->with('broken impersonations');

    it('refuses an api request as an ended impersonation', function (Closure $harnessFor, string $now) {
        $harness = $harnessFor('/api/appointments', 'GET');

        expect(fn () => $harness->enforceImpersonation(new FakeClock(new DateTimeImmutable($now)))->handle($harness->request, $this->next))
            ->toThrow(ImpersonationEnded::class)
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    })->with('broken impersonations');

    it('ends the impersonation and signs whoever holds the owner guard out', function (Closure $harnessFor, string $now) {
        $harness = $harnessFor('/calendar', 'GET', ENFORCE_INERTIA_HEADERS);

        $harness->enforceImpersonation(new FakeClock(new DateTimeImmutable($now)))->handle($harness->request, $this->next);

        expect($harness->holdsImpersonation())->toBeFalse()
            ->and($harness->owners->check())->toBeFalse();
    })->with('broken impersonations');

    it('keeps the admin signed in when only the hour ran out', function () {
        $harness = impersonatingOwner('/calendar', 'GET', ENFORCE_INERTIA_HEADERS)
            ->lastActiveAt(new DateTimeImmutable(ENFORCE_TEN_MINUTES_BEFORE_EXPIRY));

        $harness->enforceImpersonation(new FakeClock(new DateTimeImmutable(ImpersonationFixtures::EXPIRES_AT)))
            ->handle($harness->request, $this->next);

        expect($harness->admins->check())->toBeTrue()
            ->and($harness->businessSelection->forgotten)->toBe([ImpersonationFixtures::ACCOUNT_ID]);
    });
});

describe('an idle admin behind the impersonation', function () {
    it('signs the admin out and ends the impersonation after thirty idle minutes and one second', function () {
        $harness = impersonatingOwner('/calendar', 'GET', ENFORCE_INERTIA_HEADERS)
            ->lastActiveAt(new DateTimeImmutable('2026-09-25T14:39:59+00:00'));

        $response = $harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next);

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe(route(PlatformRoutes::LOGIN))
            ->and($harness->admins->check())->toBeFalse()
            ->and($harness->owners->check())->toBeFalse()
            ->and($harness->holdsImpersonation())->toBeFalse()
            ->and($harness->session->has(PlatformActivity::SESSION_KEY))->toBeFalse()
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    });

    it('refuses an api request as an expired admin session', function () {
        $harness = impersonatingOwner('/api/appointments')->lastActiveAt(new DateTimeImmutable('2026-09-25T14:39:59+00:00'));

        expect(fn () => $harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next))
            ->toThrow(PlatformSessionExpired::class);
    });

    it('treats a session with no recorded admin activity as idle', function () {
        $harness = (new PlatformSessionHarness('/api/appointments'))
            ->signInAdmin()
            ->signInOwner()
            ->impersonating(ImpersonationFixtures::begun());

        expect(fn () => $harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next))
            ->toThrow(PlatformSessionExpired::class);
    });

    it('is not idle at exactly thirty minutes', function () {
        $harness = impersonatingOwner('/calendar')->lastActiveAt(new DateTimeImmutable('2026-09-25T14:40:00+00:00'));

        expect($harness->enforceImpersonation($this->clock)->handle($harness->request, $this->next))->toBe($this->expected);
    });
});
