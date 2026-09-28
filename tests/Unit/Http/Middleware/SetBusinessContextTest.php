<?php

declare(strict_types=1);

use App\Http\Exceptions\BusinessAccessDenied;
use App\Http\Exceptions\TeamAccessPaused;
use App\Http\Middleware\SetBusinessContext;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeBusinessMembership;
use Tests\Support\FakeBusinessTeamKey;
use Tests\Support\FakePausedBusinessAccess;
use Tests\TestCase;

uses(TestCase::class);

const CONTEXT_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000c00a1';

const CONTEXT_OWNED_BUSINESS_UUID = '01930000-0000-7000-8000-0000000c00b1';

const CONTEXT_JOINED_BUSINESS_UUID = '01930000-0000-7000-8000-0000000c00b2';

const CONTEXT_PAUSED_BUSINESS_UUID = '01930000-0000-7000-8000-0000000c00b3';

const CONTEXT_FOREIGN_BUSINESS_UUID = '01930000-0000-7000-8000-0000000c00b9';

function businessContextRequest(?string $accountId = CONTEXT_ACCOUNT_UUID, ?string $requestedBusiness = null): Request
{
    $request = Request::create('/api/services', 'GET');

    if ($requestedBusiness !== null) {
        $request->headers->set('X-Business', $requestedBusiness);
    }

    if ($accountId !== null) {
        $request->setUserResolver(static fn (): object => (object) ['uuid' => $accountId]);
    }

    return $request;
}

/**
 * @param  list<string>  $paused
 */
function contextPausedAccess(array $paused): FakePausedBusinessAccess
{
    return new FakePausedBusinessAccess([CONTEXT_ACCOUNT_UUID => $paused]);
}

/**
 * @param  list<string>  $accessible
 * @param  list<string>|FakePausedBusinessAccess  $paused
 */
function businessContextMiddleware(array $accessible, array|FakePausedBusinessAccess $paused): SetBusinessContext
{
    $pausedAccess = $paused instanceof FakePausedBusinessAccess ? $paused : contextPausedAccess($paused);

    return new SetBusinessContext(
        new FakeBusinessMembership([CONTEXT_ACCOUNT_UUID => $accessible]),
        new FakeBusinessTeamKey([
            CONTEXT_OWNED_BUSINESS_UUID => 11,
            CONTEXT_JOINED_BUSINESS_UUID => 12,
            CONTEXT_PAUSED_BUSINESS_UUID => 13,
        ]),
        $pausedAccess,
    );
}

function businessContextRefusal(Closure $run): DomainFailure
{
    try {
        $run();
    } catch (DomainFailure $failure) {
        return $failure;
    }

    throw new RuntimeException('The caller was let through.');
}

beforeEach(function () {
    $this->ranTheRestOfTheStack = false;
    $this->expected = new Response('the page', 200);
    $this->next = function () {
        $this->ranTheRestOfTheStack = true;

        return $this->expected;
    };
});

describe('an account with an accessible business', function () {
    it('returns the response the rest of the stack produced', function () {
        $response = businessContextMiddleware([CONTEXT_OWNED_BUSINESS_UUID], [])
            ->handle(businessContextRequest(), $this->next);

        expect($response)->toBe($this->expected);
    });

    it('binds the first accessible business when no header names one', function () {
        businessContextMiddleware([CONTEXT_OWNED_BUSINESS_UUID, CONTEXT_JOINED_BUSINESS_UUID], [])
            ->handle(businessContextRequest(), $this->next);

        expect(app(BusinessContext::class)->currentBusinessId())->toBe(CONTEXT_OWNED_BUSINESS_UUID);
    });

    it('binds the business the header names', function () {
        businessContextMiddleware([CONTEXT_OWNED_BUSINESS_UUID, CONTEXT_JOINED_BUSINESS_UUID], [])
            ->handle(businessContextRequest(requestedBusiness: CONTEXT_JOINED_BUSINESS_UUID), $this->next);

        expect(app(BusinessContext::class)->currentBusinessId())->toBe(CONTEXT_JOINED_BUSINESS_UUID);
    });

    it('sets the permission team to the key of the bound business', function () {
        businessContextMiddleware([CONTEXT_OWNED_BUSINESS_UUID, CONTEXT_JOINED_BUSINESS_UUID], [])
            ->handle(businessContextRequest(requestedBusiness: CONTEXT_JOINED_BUSINESS_UUID), $this->next);

        expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe(12);
    });

    it('lets the account in to its accessible business even when another of its memberships is paused', function () {
        $response = businessContextMiddleware([CONTEXT_OWNED_BUSINESS_UUID], [CONTEXT_PAUSED_BUSINESS_UUID])
            ->handle(businessContextRequest(), $this->next);

        expect($response)->toBe($this->expected)
            ->and(app(BusinessContext::class)->currentBusinessId())->toBe(CONTEXT_OWNED_BUSINESS_UUID);
    });

    it('never asks about paused access when the business it binds is accessible', function (?string $requestedBusiness) {
        $pausedAccess = contextPausedAccess([CONTEXT_PAUSED_BUSINESS_UUID]);

        businessContextMiddleware([CONTEXT_OWNED_BUSINESS_UUID, CONTEXT_JOINED_BUSINESS_UUID], $pausedAccess)
            ->handle(businessContextRequest(requestedBusiness: $requestedBusiness), $this->next);

        expect($pausedAccess->lookups)->toBe([]);
    })->with([
        'no header' => [null],
        'a header naming an accessible business' => [CONTEXT_JOINED_BUSINESS_UUID],
    ]);
});

describe('an account whose every membership is paused', function () {
    it('refuses with team_access_paused', function () {
        $refusal = businessContextRefusal(fn () => businessContextMiddleware([], [CONTEXT_PAUSED_BUSINESS_UUID])
            ->handle(businessContextRequest(), $this->next));

        expect($refusal)->toBeInstanceOf(TeamAccessPaused::class)
            ->and($refusal->errorCode())->toBe('team_access_paused');
    });

    it('asks about the paused access of the caller of the request', function () {
        $pausedAccess = contextPausedAccess([CONTEXT_PAUSED_BUSINESS_UUID]);

        businessContextRefusal(fn () => businessContextMiddleware([], $pausedAccess)
            ->handle(businessContextRequest(), $this->next));

        expect($pausedAccess->lookups)->toBe([CONTEXT_ACCOUNT_UUID]);
    });

    it('runs none of the rest of the stack and binds no business context', function () {
        businessContextRefusal(fn () => businessContextMiddleware([], [CONTEXT_PAUSED_BUSINESS_UUID])
            ->handle(businessContextRequest(), $this->next));

        expect($this->ranTheRestOfTheStack)->toBeFalse()
            ->and(app()->bound(BusinessContext::class))->toBeFalse();
    });

    it('refuses as paused even when the header names the paused business', function () {
        $refusal = businessContextRefusal(fn () => businessContextMiddleware([], [CONTEXT_PAUSED_BUSINESS_UUID])
            ->handle(businessContextRequest(requestedBusiness: CONTEXT_PAUSED_BUSINESS_UUID), $this->next));

        expect($refusal)->toBeInstanceOf(TeamAccessPaused::class);
    });
});

describe('a header naming a paused business', function () {
    it('refuses with team_access_paused although another business is accessible', function () {
        $refusal = businessContextRefusal(fn () => businessContextMiddleware([CONTEXT_OWNED_BUSINESS_UUID], [CONTEXT_PAUSED_BUSINESS_UUID])
            ->handle(businessContextRequest(requestedBusiness: CONTEXT_PAUSED_BUSINESS_UUID), $this->next));

        expect($refusal)->toBeInstanceOf(TeamAccessPaused::class)
            ->and($refusal->errorCode())->toBe('team_access_paused');
    });

    it('runs none of the rest of the stack and binds no business context', function () {
        businessContextRefusal(fn () => businessContextMiddleware([CONTEXT_OWNED_BUSINESS_UUID], [CONTEXT_PAUSED_BUSINESS_UUID])
            ->handle(businessContextRequest(requestedBusiness: CONTEXT_PAUSED_BUSINESS_UUID), $this->next));

        expect($this->ranTheRestOfTheStack)->toBeFalse()
            ->and(app()->bound(BusinessContext::class))->toBeFalse();
    });
});

describe('the refusals that do not involve a paused business', function () {
    it('refuses an account with no membership at all with no_business', function () {
        $refusal = businessContextRefusal(fn () => businessContextMiddleware([], [])
            ->handle(businessContextRequest(), $this->next));

        expect($refusal)->toBeInstanceOf(BusinessAccessDenied::class)
            ->and($refusal->errorCode())->toBe('no_business');
    });

    it('refuses a header naming a business the account has no membership in with business_not_accessible', function (array $paused) {
        $refusal = businessContextRefusal(fn () => businessContextMiddleware([CONTEXT_OWNED_BUSINESS_UUID], $paused)
            ->handle(businessContextRequest(requestedBusiness: CONTEXT_FOREIGN_BUSINESS_UUID), $this->next));

        expect($refusal)->toBeInstanceOf(BusinessAccessDenied::class)
            ->and($refusal->errorCode())->toBe('business_not_accessible');
    })->with([
        'with no paused membership' => [[]],
        'while another membership is paused' => [[CONTEXT_PAUSED_BUSINESS_UUID]],
    ]);

    it('refuses an unauthenticated request with no_business without asking about paused access', function () {
        $pausedAccess = contextPausedAccess([CONTEXT_PAUSED_BUSINESS_UUID]);

        $refusal = businessContextRefusal(fn () => businessContextMiddleware([], $pausedAccess)
            ->handle(businessContextRequest(accountId: null), $this->next));

        expect($refusal)->toBeInstanceOf(BusinessAccessDenied::class)
            ->and($refusal->errorCode())->toBe('no_business')
            ->and($pausedAccess->lookups)->toBe([]);
    });
});
