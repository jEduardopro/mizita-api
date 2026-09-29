<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Preferences\CookiePreferences;
use App\Shared\Contracts\BusinessContext;
use Illuminate\Http\Request;
use Tests\Support\FakeBusinessAuthorization;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeBusinessPlan;
use Tests\TestCase;

uses(TestCase::class);

const INERTIA_OTHER_BUSINESS_UUID = '01930000-0000-7000-8000-0000000f00b2';

function inertiaSharedPlan(HandleInertiaRequests $middleware): mixed
{
    return $middleware->share(Request::create('/calendar', 'GET'))['plan']();
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
    $this->middleware = new HandleInertiaRequests(new CookiePreferences, new FakeBusinessAuthorization, $this->plans);
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
