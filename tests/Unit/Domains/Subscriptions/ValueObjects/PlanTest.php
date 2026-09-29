<?php

declare(strict_types=1);

use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanEntitlements;

describe('entitlements', function () {
    it('limits a free business to three active services and nothing else', function () {
        expect(PlanEntitlements::free())->toEqual(new PlanEntitlements(
            includesTeam: false,
            maxActiveServices: 3,
            includesBookingRules: false,
            includesCalendarSync: false,
        ));
    });

    it('opens every feature to a complete business with no service limit', function () {
        expect(PlanEntitlements::complete())->toEqual(new PlanEntitlements(
            includesTeam: true,
            maxActiveServices: null,
            includesBookingRules: true,
            includesCalendarSync: true,
        ));
    });

    it('publishes the free service limit as three', function () {
        expect(PlanEntitlements::FREE_ACTIVE_SERVICE_LIMIT)->toBe(3)
            ->and(PlanEntitlements::free()->maxActiveServices)->toBe(PlanEntitlements::FREE_ACTIVE_SERVICE_LIMIT);
    });

    it('hands each plan its own entitlements', function (Plan $plan, PlanEntitlements $expected) {
        expect($plan->entitlements())->toEqual($expected);
    })->with([
        'free' => [Plan::Free, PlanEntitlements::free()],
        'complete' => [Plan::Complete, PlanEntitlements::complete()],
    ]);
});

describe('the plan names', function () {
    it('stores each plan under its lowercase english name', function (string $value, Plan $plan) {
        expect(Plan::from($value))->toBe($plan);
    })->with([
        'free' => ['free', Plan::Free],
        'complete' => ['complete', Plan::Complete],
    ]);

    it('knows no plan by a capitalised or translated name', function (string $value) {
        expect(Plan::tryFrom($value))->toBeNull();
    })->with(['Complete', 'COMPLETE', 'completo', 'gratis', 'premium', '']);
});
