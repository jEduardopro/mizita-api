<?php

declare(strict_types=1);

use App\Http\Exceptions\PermissionDenied;
use App\Http\Middleware\RequirePermission;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeBusinessAuthorization;
use Tests\Support\FakeBusinessContext;

const PERMISSION_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000000a1';

const PERMISSION_OTHER_BUSINESS_UUID = '01930000-0000-7000-8000-0000000000b2';

function permissionRequest(?string $accountId = PERMISSION_ACCOUNT_UUID): Request
{
    $request = Request::create('/api/services', 'POST');

    if ($accountId !== null) {
        $request->setUserResolver(static fn (): object => (object) ['uuid' => $accountId]);
    }

    return $request;
}

function requirePermission(
    Request $request,
    string $permission,
    FakeBusinessAuthorization $authorization,
    ?Closure $next = null,
    string $businessId = FakeBusinessContext::BUSINESS_ID,
): Response {
    return (new RequirePermission(new FakeBusinessContext($businessId), $authorization))
        ->handle($request, $next ?? static fn (): Response => new Response, $permission);
}

beforeEach(function () {
    $this->authorization = FakeBusinessAuthorization::granting(
        PERMISSION_ACCOUNT_UUID,
        FakeBusinessContext::BUSINESS_ID,
        ['view_services', 'create_service'],
        ['staff'],
    );
});

describe('letting a caller through', function () {
    it('returns the response the rest of the stack produced', function () {
        $expected = new Response('the service', 201);

        expect(requirePermission(
            permissionRequest(),
            'create_service',
            $this->authorization,
            static fn (): Response => $expected,
        ))->toBe($expected);
    });

    it('hands the untouched request to the rest of the stack', function () {
        $request = permissionRequest();
        $received = null;

        requirePermission($request, 'create_service', $this->authorization, function ($passed) use (&$received) {
            $received = $passed;

            return new Response;
        });

        expect($received)->toBe($request);
    });

    it('asks about the caller of the request and the business in context, never one a caller could name', function () {
        requirePermission(permissionRequest(), 'view_services', $this->authorization);

        expect($this->authorization->lastCheck())->toBe([
            'accountId' => PERMISSION_ACCOUNT_UUID,
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'permission' => 'view_services',
        ]);
    });
});

describe('refusing a caller', function () {
    it('refuses a permission the caller does not hold', function () {
        expect(fn () => requirePermission(permissionRequest(), 'delete_service', $this->authorization))
            ->toThrow(PermissionDenied::class);
    });

    it('runs none of the rest of the stack when it refuses', function () {
        $ran = false;

        try {
            requirePermission(permissionRequest(), 'delete_service', $this->authorization, function () use (&$ran) {
                $ran = true;

                return new Response;
            });
        } catch (PermissionDenied) {
            expect($ran)->toBeFalse();

            return;
        }

        throw new RuntimeException('The caller was let through.');
    });

    it('refuses a request nobody is authenticated on, without asking the authorization anything', function () {
        expect(fn () => requirePermission(permissionRequest(accountId: null), 'view_services', $this->authorization))
            ->toThrow(PermissionDenied::class)
            ->and($this->authorization->checks)->toBe([]);
    });

    it('refuses a permission the caller holds in another business', function () {
        expect(fn () => requirePermission(
            permissionRequest(),
            'create_service',
            $this->authorization,
            businessId: PERMISSION_OTHER_BUSINESS_UUID,
        ))->toThrow(PermissionDenied::class);
    });

    it('refuses a caller the authorization knows nothing about', function () {
        expect(fn () => requirePermission(
            permissionRequest('01930000-0000-7000-8000-0000000000a9'),
            'view_services',
            $this->authorization,
        ))->toThrow(PermissionDenied::class);
    });

    it('names the permission it was guarding nowhere in the refusal', function () {
        try {
            requirePermission(permissionRequest(), 'delete_service', $this->authorization);
        } catch (PermissionDenied $denied) {
            expect($denied->getMessage())->not->toContain('delete_service')
                ->and($denied->errorCode())->toBe('missing_permission');

            return;
        }

        throw new RuntimeException('The caller was let through.');
    });

    it('refuses the same way whether the caller lacks the permission or is not signed in', function () {
        $lacking = null;
        $anonymous = null;

        try {
            requirePermission(permissionRequest(), 'delete_service', $this->authorization);
        } catch (PermissionDenied $denied) {
            $lacking = $denied;
        }

        try {
            requirePermission(permissionRequest(accountId: null), 'delete_service', $this->authorization);
        } catch (PermissionDenied $denied) {
            $anonymous = $denied;
        }

        expect($lacking?->errorCode())->toBe($anonymous?->errorCode())
            ->and($lacking?->getMessage())->toBe($anonymous?->getMessage())
            ->and($lacking?->kind())->toBe($anonymous?->kind());
    });
});
