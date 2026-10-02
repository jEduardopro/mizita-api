<?php

declare(strict_types=1);

use App\Domains\Platform\Exceptions\ImpersonationConfinedToBusiness;
use App\Domains\Platform\Infrastructure\Impersonation\ImpersonationConfinedBusinessResolver;
use App\Http\Exceptions\BusinessAccessDenied;
use Tests\Support\Businesses\FakeCurrentBusinessResolver;
use Tests\Support\Platform\ImpersonationFixtures;
use Tests\Support\Platform\RecordingImpersonationSession;

beforeEach(function () {
    $this->memberships = new FakeCurrentBusinessResolver;
    $this->impersonations = new RecordingImpersonationSession;
    $this->resolver = new ImpersonationConfinedBusinessResolver($this->memberships, $this->impersonations);
});

describe('outside an impersonation', function () {
    it('hands the account and the requested business to the membership resolver untouched', function (?string $requestedBusinessId) {
        $memberships = new FakeCurrentBusinessResolver(ImpersonationFixtures::BUSINESS_ID);

        (new ImpersonationConfinedBusinessResolver($memberships, $this->impersonations))
            ->resolveFor(ImpersonationFixtures::ACCOUNT_ID, $requestedBusinessId);

        expect($memberships->resolutions)->toBe([
            ['accountId' => ImpersonationFixtures::ACCOUNT_ID, 'requestedBusinessId' => $requestedBusinessId],
        ]);
    })->with([
        'no business requested' => [null],
        'the business it belongs to' => [ImpersonationFixtures::BUSINESS_ID],
        'any other business' => [ImpersonationFixtures::OTHER_BUSINESS_ID],
    ]);

    it('answers with whatever business the membership resolver settles on', function () {
        $resolver = new ImpersonationConfinedBusinessResolver(
            new FakeCurrentBusinessResolver(ImpersonationFixtures::OTHER_BUSINESS_ID),
            $this->impersonations,
        );

        expect($resolver->resolveFor(ImpersonationFixtures::ACCOUNT_ID, null))->toBe(ImpersonationFixtures::OTHER_BUSINESS_ID);
    });
});

describe('while impersonating', function () {
    beforeEach(function () {
        $this->impersonations->holding(ImpersonationFixtures::begun());
    });

    it('pins the membership resolver to the impersonated business', function (?string $requestedBusinessId) {
        $resolved = $this->resolver->resolveFor(ImpersonationFixtures::ACCOUNT_ID, $requestedBusinessId);

        expect($resolved)->toBe(ImpersonationFixtures::BUSINESS_ID)
            ->and($this->memberships->resolutions)->toBe([
                ['accountId' => ImpersonationFixtures::ACCOUNT_ID, 'requestedBusinessId' => ImpersonationFixtures::BUSINESS_ID],
            ]);
    })->with([
        'no business requested, so the owner fallback is not trusted' => [null],
        'the impersonated business itself' => [ImpersonationFixtures::BUSINESS_ID],
    ]);

    it('refuses another business the owner belongs to, without consulting the memberships', function () {
        expect(fn () => $this->resolver->resolveFor(ImpersonationFixtures::ACCOUNT_ID, ImpersonationFixtures::OTHER_BUSINESS_ID))
            ->toThrow(ImpersonationConfinedToBusiness::class)
            ->and($this->memberships->resolutions)->toBe([]);
    });

    it('still lets the membership resolver refuse the impersonated business', function () {
        $this->memberships->refusingWith(BusinessAccessDenied::businessNotAccessible(ImpersonationFixtures::BUSINESS_ID));

        expect(fn () => $this->resolver->resolveFor(ImpersonationFixtures::ACCOUNT_ID, null))
            ->toThrow(BusinessAccessDenied::class);
    });
});
