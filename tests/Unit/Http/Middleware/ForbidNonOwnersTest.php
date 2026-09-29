<?php

declare(strict_types=1);

use App\Http\Exceptions\PermissionDenied;
use App\Http\Middleware\ForbidNonOwners;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeBusinessAuthorization;
use Tests\Support\FakeBusinessContext;

const FORBID_NON_OWNERS_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000f00c1';

const FORBID_NON_OWNERS_OTHER_BUSINESS_UUID = '01930000-0000-7000-8000-0000000f00c2';

function subscriptionApiRequest(?string $accountId = FORBID_NON_OWNERS_ACCOUNT_UUID): Request
{
    $request = Request::create('/api/subscription', 'GET');

    if ($accountId !== null) {
        $request->setUserResolver(static fn (): object => (object) ['uuid' => $accountId]);
    }

    return $request;
}

function forbidNonOwners(
    FakeBusinessAuthorization $authorization,
    string $businessId = FakeBusinessContext::BUSINESS_ID,
): ForbidNonOwners {
    return new ForbidNonOwners(new FakeBusinessContext($businessId), $authorization);
}

function ownerOfTheBusinessInContext(): FakeBusinessAuthorization
{
    return FakeBusinessAuthorization::granting(
        FORBID_NON_OWNERS_ACCOUNT_UUID,
        FakeBusinessContext::BUSINESS_ID,
        ['view_services'],
        ['owner'],
    );
}

beforeEach(function () {
    $this->ranTheRestOfTheStack = false;
    $this->expected = new Response('the subscription', 200);
    $this->next = function () {
        $this->ranTheRestOfTheStack = true;

        return $this->expected;
    };
});

describe('letting the owner through', function () {
    it('returns the response the rest of the stack produced', function () {
        $response = forbidNonOwners(ownerOfTheBusinessInContext())->handle(subscriptionApiRequest(), $this->next);

        expect($response)->toBe($this->expected)
            ->and($this->ranTheRestOfTheStack)->toBeTrue();
    });

    it('hands the untouched request to the rest of the stack', function () {
        $request = subscriptionApiRequest();
        $received = null;

        forbidNonOwners(ownerOfTheBusinessInContext())->handle($request, function ($passed) use (&$received) {
            $received = $passed;

            return new Response;
        });

        expect($received)->toBe($request);
    });

    it('asks about the caller of the request in the business in context', function () {
        $authorization = ownerOfTheBusinessInContext();

        forbidNonOwners($authorization)->handle(subscriptionApiRequest(), $this->next);

        expect($authorization->lookups)->toBe([
            ['accountId' => FORBID_NON_OWNERS_ACCOUNT_UUID, 'businessId' => FakeBusinessContext::BUSINESS_ID],
        ]);
    });
});

describe('refusing everyone else', function () {
    it('refuses a caller whose roles do not include owner, as a forbidden domain failure', function (array $roles) {
        $authorization = FakeBusinessAuthorization::granting(
            FORBID_NON_OWNERS_ACCOUNT_UUID,
            FakeBusinessContext::BUSINESS_ID,
            ['view_services'],
            $roles,
        );

        try {
            forbidNonOwners($authorization)->handle(subscriptionApiRequest(), $this->next);
            $refusal = null;
        } catch (PermissionDenied $denied) {
            $refusal = $denied;
        }

        expect($refusal)->toBeInstanceOf(PermissionDenied::class)
            ->and($refusal?->errorCode())->toBe('missing_permission')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Forbidden)
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    })->with([
        'staff' => [['staff']],
        'no role at all' => [[]],
        'a role merely resembling owner' => [['Owner']],
    ]);

    it('refuses the owner of another business than the one in context', function () {
        expect(fn () => forbidNonOwners(ownerOfTheBusinessInContext(), FORBID_NON_OWNERS_OTHER_BUSINESS_UUID)
            ->handle(subscriptionApiRequest(), $this->next))
            ->toThrow(PermissionDenied::class)
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    });

    it('refuses a request nobody is authenticated on without asking the authorization anything', function () {
        $authorization = new FakeBusinessAuthorization;

        expect(fn () => forbidNonOwners($authorization)->handle(subscriptionApiRequest(accountId: null), $this->next))
            ->toThrow(PermissionDenied::class)
            ->and($authorization->lookups)->toBe([])
            ->and($this->ranTheRestOfTheStack)->toBeFalse();
    });
});
