<?php

declare(strict_types=1);

use App\Http\Middleware\RedirectIfOnboarded;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeBusinessMembership;
use Tests\TestCase;

uses(TestCase::class);

const ONBOARDED_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000e00a1';

const ONBOARDED_OTHER_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000e00a2';

const ONBOARDED_BUSINESS_UUID = '01930000-0000-7000-8000-0000000e00b1';

const ONBOARDED_SECOND_BUSINESS_UUID = '01930000-0000-7000-8000-0000000e00b2';

function onboardingRequest(): Request
{
    $request = Request::create('/onboarding', 'GET');
    $request->setUserResolver(static fn (): object => (object) ['uuid' => ONBOARDED_ACCOUNT_UUID]);

    return $request;
}

/**
 * @param  array<string, list<string>>  $accessibleBusinessesByAccount
 */
function redirectIfOnboarded(array $accessibleBusinessesByAccount): RedirectIfOnboarded
{
    return new RedirectIfOnboarded(new FakeBusinessMembership($accessibleBusinessesByAccount));
}

beforeEach(function () {
    $this->ranTheRestOfTheStack = false;
    $this->expected = new Response('the onboarding page', 200);
    $this->next = function () {
        $this->ranTheRestOfTheStack = true;

        return $this->expected;
    };
});

it('sends an account with an accessible business to the dashboard', function (array $accessible) {
    $response = redirectIfOnboarded([ONBOARDED_ACCOUNT_UUID => $accessible])
        ->handle(onboardingRequest(), $this->next);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(route('dashboard'))
        ->and($this->ranTheRestOfTheStack)->toBeFalse();
})->with([
    'one business' => [[ONBOARDED_BUSINESS_UUID]],
    'several businesses' => [[ONBOARDED_BUSINESS_UUID, ONBOARDED_SECOND_BUSINESS_UUID]],
]);

it('renders onboarding when no membership grants the account access to a business', function () {
    $response = redirectIfOnboarded([ONBOARDED_ACCOUNT_UUID => []])->handle(onboardingRequest(), $this->next);

    expect($response)->toBe($this->expected)
        ->and($this->ranTheRestOfTheStack)->toBeTrue();
});

it('renders onboarding for an account the membership knows nothing about', function () {
    $response = redirectIfOnboarded([])->handle(onboardingRequest(), $this->next);

    expect($response)->toBe($this->expected);
});

it('decides on the memberships of the caller, not of another account', function () {
    $response = redirectIfOnboarded([ONBOARDED_OTHER_ACCOUNT_UUID => [ONBOARDED_BUSINESS_UUID]])
        ->handle(onboardingRequest(), $this->next);

    expect($response)->toBe($this->expected);
});
