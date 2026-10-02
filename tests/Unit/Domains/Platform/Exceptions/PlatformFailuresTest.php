<?php

declare(strict_types=1);

use App\Domains\Platform\Exceptions\BusinessHasNoOwner;
use App\Domains\Platform\Exceptions\BusinessOwnerDeactivated;
use App\Domains\Platform\Exceptions\ImpersonatedBusinessNotFound;
use App\Domains\Platform\Exceptions\ImpersonationConfinedToBusiness;
use App\Domains\Platform\Exceptions\ImpersonationEnded;
use App\Domains\Platform\Exceptions\InvalidPlatformAdminEmail;
use App\Domains\Platform\Exceptions\InvalidPlatformAdminName;
use App\Domains\Platform\Exceptions\InvalidPlatformTwoFactorCode;
use App\Domains\Platform\Exceptions\PlatformAdminAlreadyExists;
use App\Domains\Platform\Exceptions\PlatformAdminPasswordTooShort;
use App\Domains\Platform\Exceptions\PlatformSessionExpired;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Platform\ImpersonationFixtures;

/**
 * @return array<string, array{DomainFailure, string, DomainFailureKind}>
 */
function platformFailures(): array
{
    return [
        'an empty admin email' => [InvalidPlatformAdminEmail::empty(), 'invalid_platform_admin_email', DomainFailureKind::Invalid],
        'a malformed admin email' => [InvalidPlatformAdminEmail::malformed('grace'), 'invalid_platform_admin_email', DomainFailureKind::Invalid],
        'an admin email too long' => [InvalidPlatformAdminEmail::tooLong(255), 'invalid_platform_admin_email', DomainFailureKind::Invalid],
        'an empty admin name' => [InvalidPlatformAdminName::empty(), 'invalid_platform_admin_name', DomainFailureKind::Invalid],
        'an admin name too long' => [InvalidPlatformAdminName::tooLong(255), 'invalid_platform_admin_name', DomainFailureKind::Invalid],
        'a password too short' => [PlatformAdminPasswordTooShort::below(12), 'platform_admin_password_too_short', DomainFailureKind::Invalid],
        'a malformed authenticator code' => [InvalidPlatformTwoFactorCode::malformed(), 'invalid_platform_two_factor_code', DomainFailureKind::Invalid],
        'an authenticator code that did not confirm' => [InvalidPlatformTwoFactorCode::notConfirmedBy('grace@mizita.test'), 'invalid_platform_two_factor_code', DomainFailureKind::Invalid],
        'an admin already registered' => [PlatformAdminAlreadyExists::withEmail('grace@mizita.test'), 'platform_admin_already_exists', DomainFailureKind::Conflict],
        'a business nobody can impersonate' => [ImpersonatedBusinessNotFound::withId(ImpersonationFixtures::BUSINESS_ID), 'impersonated_business_not_found', DomainFailureKind::NotFound],
        'a business with no owner' => [BusinessHasNoOwner::forBusiness(ImpersonationFixtures::BUSINESS_ID), 'business_has_no_owner', DomainFailureKind::Conflict],
        'a deactivated owner' => [BusinessOwnerDeactivated::forBusiness(ImpersonationFixtures::BUSINESS_ID), 'business_owner_deactivated', DomainFailureKind::Conflict],
        'an expired admin session' => [PlatformSessionExpired::signedOut(), 'platform_session_expired', DomainFailureKind::Unauthenticated],
        'an impersonation that ended' => [ImpersonationEnded::noLongerValid(), 'impersonation_ended', DomainFailureKind::Unauthenticated],
        'a business outside the impersonation' => [ImpersonationConfinedToBusiness::outside(ImpersonationFixtures::BUSINESS_ID, ImpersonationFixtures::OTHER_BUSINESS_ID), 'business_not_accessible', DomainFailureKind::Forbidden],
    ];
}

it('answers with the stable error code the client is shown a sentence for', function (
    DomainFailure $failure,
    string $code,
) {
    expect($failure->errorCode())->toBe($code);
})->with(platformFailures());

it('classifies the refusal so the edge knows which status to render', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
) {
    expect($failure->kind())->toBe($kind);
})->with(platformFailures());

it('carries the interface the renderer is registered against', function (DomainFailure $failure) {
    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure)->toBeInstanceOf(Throwable::class);
})->with(platformFailures());

it('has a sentence to show the caller in every locale', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
    string $locale,
) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][$code] ?? '')->toBeString()->not->toBe('');
})->with(platformFailures())->with(['en', 'es']);

it('says what it turned down', function () {
    expect(InvalidPlatformAdminEmail::empty()->getMessage())
        ->toBe('A platform admin email cannot be empty.')
        ->and(InvalidPlatformAdminEmail::malformed('grace')->getMessage())
        ->toBe('[grace] is not a valid email address.')
        ->and(InvalidPlatformAdminEmail::tooLong(255)->getMessage())
        ->toBe('A platform admin email takes up to [255] characters.')
        ->and(InvalidPlatformAdminName::empty()->getMessage())
        ->toBe('A platform admin name cannot be empty.')
        ->and(InvalidPlatformAdminName::tooLong(255)->getMessage())
        ->toBe('A platform admin name takes up to [255] characters.')
        ->and(PlatformAdminPasswordTooShort::below(12)->getMessage())
        ->toBe('A platform admin password takes at least [12] characters.')
        ->and(InvalidPlatformTwoFactorCode::malformed()->getMessage())
        ->toBe('An authenticator app code is six digits.')
        ->and(InvalidPlatformTwoFactorCode::notConfirmedBy('grace@mizita.test')->getMessage())
        ->toBe('The authenticator app code did not confirm the enrollment of [grace@mizita.test].')
        ->and(PlatformAdminAlreadyExists::withEmail('grace@mizita.test')->getMessage())
        ->toBe('A platform admin with the email [grace@mizita.test] already exists.')
        ->and(ImpersonatedBusinessNotFound::withId('b-1')->getMessage())
        ->toBe('No business [b-1] can be impersonated.')
        ->and(BusinessHasNoOwner::forBusiness('b-1')->getMessage())
        ->toBe('Business [b-1] has no owner account to impersonate.')
        ->and(BusinessOwnerDeactivated::forBusiness('b-1')->getMessage())
        ->toBe('The owner account of business [b-1] is deactivated.');
});
