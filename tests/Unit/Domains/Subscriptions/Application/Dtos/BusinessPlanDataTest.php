<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\BusinessPlanData;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanEntitlements;

it('pairs a plan with its own entitlements', function (Plan $plan, PlanEntitlements $entitlements) {
    $data = BusinessPlanData::of($plan);

    expect($data->plan)->toBe($plan)
        ->and($data->entitlements)->toEqual($entitlements);
})->with([
    'free' => [Plan::Free, PlanEntitlements::free()],
    'complete' => [Plan::Complete, PlanEntitlements::complete()],
]);
