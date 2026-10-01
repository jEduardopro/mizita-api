<?php

declare(strict_types=1);

use App\Http\Middleware\RedirectIfOnboarded;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeBusinessOwnership;
use Tests\TestCase;

uses(TestCase::class);

const ONBOARDED_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000e00a1';

const ONBOARDED_OTHER_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000e00a2';

function onboardingRequest(): Request
{
    $request = Request::create('/onboarding', 'GET');
    $request->setUserResolver(static fn (): object => (object) ['uuid' => ONBOARDED_ACCOUNT_UUID]);

    return $request;
}

beforeEach(function () {
    $this->ranTheRestOfTheStack = false;
    $this->expected = new Response('the onboarding page', 200);
    $this->next = function () {
        $this->ranTheRestOfTheStack = true;

        return $this->expected;
    };
});

it('sends an account that owns an open business to the dashboard', function () {
    $response = (new RedirectIfOnboarded(new FakeBusinessOwnership([ONBOARDED_ACCOUNT_UUID])))
        ->handle(onboardingRequest(), $this->next);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(route('dashboard'))
        ->and($this->ranTheRestOfTheStack)->toBeFalse();
});

it('lets a staff-only account reach onboarding, because only ownership counts as onboarded', function () {
    $response = (new RedirectIfOnboarded(new FakeBusinessOwnership))->handle(onboardingRequest(), $this->next);

    expect($response)->toBe($this->expected)
        ->and($this->ranTheRestOfTheStack)->toBeTrue();
});

it('asks about the ownership of the caller account', function () {
    $ownership = new FakeBusinessOwnership;

    (new RedirectIfOnboarded($ownership))->handle(onboardingRequest(), $this->next);

    expect($ownership->lookups)->toBe([ONBOARDED_ACCOUNT_UUID]);
});

it('renders onboarding when only another account owns an open business', function () {
    $response = (new RedirectIfOnboarded(new FakeBusinessOwnership([ONBOARDED_OTHER_ACCOUNT_UUID])))
        ->handle(onboardingRequest(), $this->next);

    expect($response)->toBe($this->expected);
});

it('never redirects a request that carries no authenticated account', function () {
    $response = (new RedirectIfOnboarded(new FakeBusinessOwnership([ONBOARDED_ACCOUNT_UUID])))
        ->handle(Request::create('/onboarding', 'GET'), $this->next);

    expect($response)->toBe($this->expected);
});
