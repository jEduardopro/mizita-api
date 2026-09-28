<?php

declare(strict_types=1);

use App\Http\Middleware\RequireBusinessMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeBusinessMembership;
use Tests\Support\FakePausedBusinessAccess;
use Tests\TestCase;

uses(TestCase::class);

const MEMBERSHIP_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000d00a1';

const MEMBERSHIP_BUSINESS_UUID = '01930000-0000-7000-8000-0000000d00b1';

const MEMBERSHIP_PAUSED_BUSINESS_UUID = '01930000-0000-7000-8000-0000000d00b2';

function membershipRequest(): Request
{
    $request = Request::create('/calendar', 'GET');
    $request->setUserResolver(static fn (): object => (object) ['uuid' => MEMBERSHIP_ACCOUNT_UUID]);

    return $request;
}

beforeEach(function () {
    $this->ranTheRestOfTheStack = false;
    $this->expected = new Response('the page', 200);
    $this->next = function () {
        $this->ranTheRestOfTheStack = true;

        return $this->expected;
    };
});

/**
 * @param  list<string>  $accessible
 */
function requireBusinessMembership(array $accessible, FakePausedBusinessAccess $pausedAccess): RequireBusinessMembership
{
    return new RequireBusinessMembership(
        new FakeBusinessMembership([MEMBERSHIP_ACCOUNT_UUID => $accessible]),
        $pausedAccess,
    );
}

function membershipPausedAccess(string ...$paused): FakePausedBusinessAccess
{
    return new FakePausedBusinessAccess([MEMBERSHIP_ACCOUNT_UUID => array_values($paused)]);
}

it('lets an account with an accessible business through to the rest of the stack', function () {
    $response = requireBusinessMembership([MEMBERSHIP_BUSINESS_UUID], membershipPausedAccess(MEMBERSHIP_PAUSED_BUSINESS_UUID))
        ->handle(membershipRequest(), $this->next);

    expect($response)->toBe($this->expected);
});

it('never asks about paused access when the account has an accessible business', function () {
    $pausedAccess = membershipPausedAccess(MEMBERSHIP_PAUSED_BUSINESS_UUID);

    requireBusinessMembership([MEMBERSHIP_BUSINESS_UUID], $pausedAccess)->handle(membershipRequest(), $this->next);

    expect($pausedAccess->lookups)->toBe([]);
});

it('sends an account whose every membership is paused to the paused team access screen', function () {
    $response = requireBusinessMembership([], membershipPausedAccess(MEMBERSHIP_PAUSED_BUSINESS_UUID))
        ->handle(membershipRequest(), $this->next);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(route('team-access.paused'))
        ->and($this->ranTheRestOfTheStack)->toBeFalse();
});

it('asks about the paused access of the caller of the request', function () {
    $pausedAccess = membershipPausedAccess(MEMBERSHIP_PAUSED_BUSINESS_UUID);

    requireBusinessMembership([], $pausedAccess)->handle(membershipRequest(), $this->next);

    expect($pausedAccess->lookups)->toBe([MEMBERSHIP_ACCOUNT_UUID]);
});

it('sends an account with no membership at all to onboarding', function () {
    $response = requireBusinessMembership([], membershipPausedAccess())
        ->handle(membershipRequest(), $this->next);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(route('onboarding'))
        ->and($this->ranTheRestOfTheStack)->toBeFalse();
});
