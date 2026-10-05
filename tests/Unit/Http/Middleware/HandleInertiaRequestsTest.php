<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Preferences\CookiePreferences;
use App\Http\PublicLinks\PublicLinks;
use App\Shared\Contracts\BusinessContext;
use Illuminate\Http\Request;
use Tests\Support\FakeBusinessAuthorization;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeBusinessPlan;
use Tests\Support\FakeImpersonationStatus;
use Tests\Support\FakeSignedInPlatformAdmin;
use Tests\TestCase;

uses(TestCase::class);

const INERTIA_OTHER_BUSINESS_UUID = '01930000-0000-7000-8000-0000000f00b2';

function inertiaSharedPlan(HandleInertiaRequests $middleware): mixed
{
    return inertiaSharedProp($middleware, 'plan');
}

function inertiaSharedProp(HandleInertiaRequests $middleware, string $prop): mixed
{
    return $middleware->share(Request::create('/calendar', 'GET'))[$prop]();
}

beforeEach(function () {
    $this->completePlan = [
        'name' => 'complete',
        'entitlements' => ['team' => true, 'max_active_services' => null, 'booking_rules' => true, 'calendar_sync' => true],
    ];
    $this->freePlan = [
        'name' => 'free',
        'entitlements' => ['team' => false, 'max_active_services' => 3, 'booking_rules' => false, 'calendar_sync' => false],
    ];
    $this->plans = new FakeBusinessPlan([
        FakeBusinessContext::BUSINESS_ID => $this->completePlan,
        INERTIA_OTHER_BUSINESS_UUID => $this->freePlan,
    ]);
    $this->middleware = new HandleInertiaRequests(
        new CookiePreferences,
        new FakeBusinessAuthorization,
        $this->plans,
        new FakeImpersonationStatus,
        new FakeSignedInPlatformAdmin,
        new PublicLinks,
    );
});

it('shares no plan when no business context is bound', function () {
    expect(inertiaSharedPlan($this->middleware))->toBeNull()
        ->and($this->plans->describedBusinessIds)->toBe([]);
});

it('shares the plan of the business in context', function () {
    app()->instance(BusinessContext::class, new FakeBusinessContext);

    expect(inertiaSharedPlan($this->middleware))->toBe($this->completePlan)
        ->and($this->plans->describedBusinessIds)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('shares the plan of whichever business is in context, never a fixed one', function () {
    app()->instance(BusinessContext::class, new FakeBusinessContext(INERTIA_OTHER_BUSINESS_UUID));

    expect(inertiaSharedPlan($this->middleware))->toBe($this->freePlan);
});

it('describes the plan only when the page reads the prop', function () {
    app()->instance(BusinessContext::class, new FakeBusinessContext);

    $this->middleware->share(Request::create('/calendar', 'GET'));

    expect($this->plans->describedBusinessIds)->toBe([]);
});

describe('the impersonation prop', function () {
    it('shares null when no platform admin is impersonating anyone', function () {
        expect(inertiaSharedProp($this->middleware, 'impersonation'))->toBeNull();
    });

    it('shares the business, the owner and the instant the impersonation expires', function () {
        $impersonation = [
            'business_name' => 'Barbería Ñandú',
            'owner_name' => 'Ada Lovelace',
            'expires_at' => '2026-09-25T16:00:00+00:00',
        ];
        $middleware = new HandleInertiaRequests(
            new CookiePreferences,
            new FakeBusinessAuthorization,
            $this->plans,
            new FakeImpersonationStatus($impersonation),
            new FakeSignedInPlatformAdmin,
            new PublicLinks,
        );

        $shared = inertiaSharedProp($middleware, 'impersonation');

        expect($shared)->toBe($impersonation)
            ->and(array_keys($shared))->toBe(['business_name', 'owner_name', 'expires_at'])
            ->and(DateTimeImmutable::createFromFormat(DATE_ATOM, $shared['expires_at']))->not->toBeFalse();
    });

    it('describes the impersonation only when the page reads the prop', function () {
        $impersonations = new FakeImpersonationStatus([
            'business_name' => 'Barbería Ñandú',
            'owner_name' => 'Ada Lovelace',
            'expires_at' => '2026-09-25T16:00:00+00:00',
        ]);
        $middleware = new HandleInertiaRequests(
            new CookiePreferences,
            new FakeBusinessAuthorization,
            $this->plans,
            $impersonations,
            new FakeSignedInPlatformAdmin,
            new PublicLinks,
        );

        $middleware->share(Request::create('/calendar', 'GET'));

        expect($impersonations->descriptions)->toBe(0);
    });
});

describe('the platformAdmin prop', function () {
    it('shares null when no platform admin is signed in', function () {
        expect(inertiaSharedProp($this->middleware, 'platformAdmin'))->toBeNull();
    });

    it('shares the name and the email of the signed in platform admin', function () {
        $middleware = new HandleInertiaRequests(
            new CookiePreferences,
            new FakeBusinessAuthorization,
            $this->plans,
            new FakeImpersonationStatus,
            new FakeSignedInPlatformAdmin(['name' => 'Grace Hopper', 'email' => 'grace@mizita.test']),
            new PublicLinks,
        );

        expect(inertiaSharedProp($middleware, 'platformAdmin'))
            ->toBe(['name' => 'Grace Hopper', 'email' => 'grace@mizita.test']);
    });

    it('describes the platform admin only when the page reads the prop', function () {
        $admins = new FakeSignedInPlatformAdmin(['name' => 'Grace Hopper', 'email' => 'grace@mizita.test']);
        $middleware = new HandleInertiaRequests(
            new CookiePreferences,
            new FakeBusinessAuthorization,
            $this->plans,
            new FakeImpersonationStatus,
            $admins,
            new PublicLinks,
        );

        $middleware->share(Request::create('/calendar', 'GET'));

        expect($admins->descriptions)->toBe(0);
    });
});

describe('the publicLinks prop', function () {
    it('shares exactly the facebook, instagram and contact links', function () {
        config(['public-links' => ['facebook' => null, 'instagram' => null, 'contact' => null]]);

        expect(array_keys(inertiaSharedProp($this->middleware, 'publicLinks')))
            ->toBe(['facebook', 'instagram', 'contact']);
    });

    it('shares the links the configuration describes', function () {
        config(['public-links' => [
            'facebook' => '  https://www.facebook.com/mizita  ',
            'instagram' => 'javascript:alert(1)',
            'contact' => 'mailto:hola@mizita.app',
        ]]);

        expect(inertiaSharedProp($this->middleware, 'publicLinks'))->toBe([
            'facebook' => 'https://www.facebook.com/mizita',
            'instagram' => null,
            'contact' => 'mailto:hola@mizita.app',
        ]);
    });

    it('shares the links lazily, as a closure the page resolves', function () {
        expect($this->middleware->share(Request::create('/calendar', 'GET'))['publicLinks'])
            ->toBeInstanceOf(Closure::class);
    });
});
