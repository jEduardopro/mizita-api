<?php

declare(strict_types=1);

use App\Http\Middleware\RequireBusinessOwner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeBusinessAuthorization;
use Tests\Support\FakeBusinessContext;
use Tests\TestCase;

uses(TestCase::class);

const OWNER_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000f00a1';

const OWNER_OTHER_BUSINESS_UUID = '01930000-0000-7000-8000-0000000f00b2';

function planSettingsRequest(?string $accountId = OWNER_ACCOUNT_UUID): Request
{
    $request = Request::create('/settings/plan', 'GET');

    if ($accountId !== null) {
        $request->setUserResolver(static fn (): object => (object) ['uuid' => $accountId]);
    }

    return $request;
}

function requireBusinessOwner(
    FakeBusinessAuthorization $authorization,
    string $businessId = FakeBusinessContext::BUSINESS_ID,
): RequireBusinessOwner {
    return new RequireBusinessOwner(new FakeBusinessContext($businessId), $authorization);
}

beforeEach(function () {
    $this->ranTheRestOfTheStack = false;
    $this->expected = new Response('the plan page', 200);
    $this->next = function () {
        $this->ranTheRestOfTheStack = true;

        return $this->expected;
    };
});

describe('letting the owner through', function () {
    beforeEach(function () {
        $this->authorization = FakeBusinessAuthorization::granting(
            OWNER_ACCOUNT_UUID,
            FakeBusinessContext::BUSINESS_ID,
            ['view_services'],
            ['owner'],
        );
    });

    it('returns the response the rest of the stack produced', function () {
        $response = requireBusinessOwner($this->authorization)->handle(planSettingsRequest(), $this->next);

        expect($response)->toBe($this->expected)
            ->and($this->ranTheRestOfTheStack)->toBeTrue();
    });

    it('hands the untouched request to the rest of the stack', function () {
        $request = planSettingsRequest();
        $received = null;

        requireBusinessOwner($this->authorization)->handle($request, function ($passed) use (&$received) {
            $received = $passed;

            return new Response;
        });

        expect($received)->toBe($request);
    });

    it('asks about the caller of the request in the business in context', function () {
        requireBusinessOwner($this->authorization)->handle(planSettingsRequest(), $this->next);

        expect($this->authorization->lookups)->toBe([
            ['accountId' => OWNER_ACCOUNT_UUID, 'businessId' => FakeBusinessContext::BUSINESS_ID],
        ]);
    });
});

describe('sending everyone else to the calendar', function () {
    it('redirects a caller whose roles do not include owner', function (array $roles) {
        $authorization = FakeBusinessAuthorization::granting(
            OWNER_ACCOUNT_UUID,
            FakeBusinessContext::BUSINESS_ID,
            ['view_services'],
            $roles,
        );

        $response = requireBusinessOwner($authorization)->handle(planSettingsRequest(), $this->next);

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe(route('calendar'))
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    })->with([
        'staff' => [['staff']],
        'no role at all' => [[]],
        'a role merely resembling owner' => [['Owner']],
    ]);

    it('redirects an owner of another business than the one in context', function () {
        $authorization = FakeBusinessAuthorization::granting(
            OWNER_ACCOUNT_UUID,
            FakeBusinessContext::BUSINESS_ID,
            ['view_services'],
            ['owner'],
        );

        $response = requireBusinessOwner($authorization, OWNER_OTHER_BUSINESS_UUID)
            ->handle(planSettingsRequest(), $this->next);

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe(route('calendar'))
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    });

    it('redirects a request nobody is authenticated on without asking the authorization anything', function () {
        $authorization = new FakeBusinessAuthorization;

        $response = requireBusinessOwner($authorization)->handle(planSettingsRequest(accountId: null), $this->next);

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe(route('calendar'))
            ->and($this->ranTheRestOfTheStack)->toBeFalse()
            ->and($authorization->lookups)->toBe([]);
    });
});
