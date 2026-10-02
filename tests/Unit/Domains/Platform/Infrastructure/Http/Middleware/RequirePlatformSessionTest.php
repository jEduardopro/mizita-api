<?php

declare(strict_types=1);

use App\Domains\Platform\Exceptions\PlatformSessionExpired;
use App\Domains\Platform\Infrastructure\Auth\PlatformActivity;
use App\Domains\Platform\Infrastructure\Http\PlatformRoutes;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeClock;
use Tests\Support\Platform\ImpersonationFixtures;
use Tests\Support\Platform\PlatformSessionHarness;
use Tests\TestCase;

uses(TestCase::class);

const PLATFORM_SESSION_PAGE = '/mizita-admin/businesses';

const PLATFORM_SESSION_API = '/api/platform/businesses';

const PLATFORM_SESSION_INERTIA_HEADERS = ['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'];

beforeEach(function () {
    $this->clock = new FakeClock(ImpersonationFixtures::now());
    $this->ranTheRestOfTheStack = false;
    $this->expected = new Response('the page', 200);
    $this->next = function () {
        $this->ranTheRestOfTheStack = true;

        return $this->expected;
    };
});

describe('with no platform admin signed in', function () {
    it('sends a page visit to the admin login', function (array $headers) {
        $harness = new PlatformSessionHarness(PLATFORM_SESSION_PAGE, 'GET', $headers);

        $response = $harness->requirePlatformSession($this->clock)->handle($harness->request, $this->next);

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe(route(PlatformRoutes::LOGIN))
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    })->with([
        'a plain browser visit' => [[]],
        'an inertia visit' => [PLATFORM_SESSION_INERTIA_HEADERS],
    ]);

    it('refuses an api request as an expired admin session', function () {
        $harness = new PlatformSessionHarness(PLATFORM_SESSION_API);

        expect(fn () => $harness->requirePlatformSession($this->clock)->handle($harness->request, $this->next))
            ->toThrow(PlatformSessionExpired::class)
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    });

    it('turns away an ordinary account signed in on the owner guard', function () {
        $harness = (new PlatformSessionHarness(PLATFORM_SESSION_API))->signInOwner();

        expect(fn () => $harness->requirePlatformSession($this->clock)->handle($harness->request, $this->next))
            ->toThrow(PlatformSessionExpired::class);
    });

    it('records no admin activity', function () {
        $harness = new PlatformSessionHarness(PLATFORM_SESSION_PAGE);

        $harness->requirePlatformSession($this->clock)->handle($harness->request, $this->next);

        expect($harness->session->has(PlatformActivity::SESSION_KEY))->toBeFalse();
    });
});

describe('with an active platform admin', function () {
    it('lets the request through', function () {
        $harness = (new PlatformSessionHarness(PLATFORM_SESSION_API))->signInAdmin()->lastActiveAt(ImpersonationFixtures::now());

        expect($harness->requirePlatformSession($this->clock)->handle($harness->request, $this->next))->toBe($this->expected);
    });

    it('records the request as the latest admin activity', function () {
        $harness = (new PlatformSessionHarness(PLATFORM_SESSION_API))->signInAdmin()->lastActiveAt(ImpersonationFixtures::now());
        $this->clock->advance('PT12M');

        $harness->requirePlatformSession($this->clock)->handle($harness->request, $this->next);

        expect($harness->session->get(PlatformActivity::SESSION_KEY))->toBe($this->clock->now()->getTimestamp());
    });

    it('is not idle at exactly thirty minutes', function () {
        $harness = (new PlatformSessionHarness(PLATFORM_SESSION_API))->signInAdmin()->lastActiveAt(ImpersonationFixtures::now());
        $this->clock->advance('PT30M');

        expect($harness->requirePlatformSession($this->clock)->handle($harness->request, $this->next))->toBe($this->expected);
    });
});

describe('with an idle platform admin', function () {
    beforeEach(function () {
        $this->clock->advance('PT30M1S');
    });

    it('signs the admin out after thirty idle minutes and one second', function () {
        $harness = (new PlatformSessionHarness(PLATFORM_SESSION_PAGE))->signInAdmin()->lastActiveAt(ImpersonationFixtures::now());

        $response = $harness->requirePlatformSession($this->clock)->handle($harness->request, $this->next);

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe(route(PlatformRoutes::LOGIN))
            ->and($harness->admins->check())->toBeFalse()
            ->and($harness->session->has(PlatformActivity::SESSION_KEY))->toBeFalse()
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    });

    it('refuses an api request as an expired admin session', function () {
        $harness = (new PlatformSessionHarness(PLATFORM_SESSION_API))->signInAdmin()->lastActiveAt(ImpersonationFixtures::now());

        expect(fn () => $harness->requirePlatformSession($this->clock)->handle($harness->request, $this->next))
            ->toThrow(PlatformSessionExpired::class)
            ->and($harness->admins->check())->toBeFalse();
    });

    it('ends an impersonation the idle admin left running', function () {
        $harness = (new PlatformSessionHarness(PLATFORM_SESSION_PAGE))
            ->signInAdmin()
            ->signInOwner()
            ->impersonating(ImpersonationFixtures::begun())
            ->lastActiveAt(ImpersonationFixtures::now());

        $harness->requirePlatformSession($this->clock)->handle($harness->request, $this->next);

        expect($harness->holdsImpersonation())->toBeFalse()
            ->and($harness->owners->check())->toBeFalse()
            ->and($harness->businessSelection->forgotten)->toBe([ImpersonationFixtures::ACCOUNT_ID]);
    });

    it('treats a signed in admin with no recorded activity as idle', function () {
        $harness = (new PlatformSessionHarness(PLATFORM_SESSION_API))->signInAdmin();

        expect(fn () => $harness->requirePlatformSession($this->clock)->handle($harness->request, $this->next))
            ->toThrow(PlatformSessionExpired::class);
    });
});
