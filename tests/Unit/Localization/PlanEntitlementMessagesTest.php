<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function planEntitlementErrorMessages(string $locale): array
{
    $catalogue = require dirname(__DIR__, 3).'/lang/'.$locale.'/messages.php';

    return $catalogue['errors'];
}

dataset('plan entitlement error codes', [
    'active_service_limit_reached',
    'team_requires_complete_plan',
    'booking_rules_require_complete_plan',
    'calendar_sync_requires_complete_plan',
    'team_access_paused',
    'staff_member_paused',
]);

it('has a message for the plan refusal in both locales', function (string $code) {
    expect(planEntitlementErrorMessages('en')[$code] ?? null)->toBeString()->not->toBe('')
        ->and(planEntitlementErrorMessages('es')[$code] ?? null)->toBeString()->not->toBe('');
})->with('plan entitlement error codes');

it('actually translates the plan refusal instead of copying the english into spanish', function (string $code) {
    expect(planEntitlementErrorMessages('es')[$code])->not->toBe(planEntitlementErrorMessages('en')[$code]);
})->with('plan entitlement error codes');
