<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\AccountDeletionPreviewData;
use App\Domains\Accounts\Infrastructure\Http\Resources\AccountDeletionPreviewResource;
use App\Domains\Accounts\ValueObjects\DeletionBlocker;
use App\Domains\Accounts\ValueObjects\OwnedBusinessSnapshot;
use Tests\Support\Accounts\AccountDeletionFixtures;
use Tests\TestCase;

uses(TestCase::class);

function deletionPreview(
    ?OwnedBusinessSnapshot $ownedBusiness = null,
    int $upcomingAppointmentsCount = 0,
    ?DeletionBlocker $blockedBy = null,
    bool $hasPassword = true,
    string $gracePeriodEndsAt = AccountDeletionFixtures::GRACE_PERIOD_ENDS_AT,
): AccountDeletionPreviewData {
    return new AccountDeletionPreviewData(
        email: AccountDeletionFixtures::EMAIL,
        hasPassword: $hasPassword,
        ownedBusiness: $ownedBusiness,
        upcomingAppointmentsCount: $upcomingAppointmentsCount,
        blockedBy: $blockedBy,
        gracePeriodEndsAt: new DateTimeImmutable($gracePeriodEndsAt),
    );
}

/**
 * @return array<string, mixed>
 */
function serializedDeletionPreview(AccountDeletionPreviewData $preview): array
{
    return AccountDeletionPreviewResource::make($preview)->response()->getData(true);
}

it('serializes the preview of an account that owns no business, wrapped in data', function () {
    expect(serializedDeletionPreview(deletionPreview()))->toBe(['data' => [
        'email' => 'ada@example.com',
        'has_password' => true,
        'owned_business' => null,
        'upcoming_appointments_count' => 0,
        'blocked_by' => null,
        'grace_period_ends_at' => '2026-01-31T12:00:00+00:00',
    ]]);
});

it('serializes the owned business by its uuid and name, and nothing else', function () {
    $payload = serializedDeletionPreview(deletionPreview(ownedBusiness: AccountDeletionFixtures::ownedBusiness(), upcomingAppointmentsCount: 4))['data'];

    expect($payload['owned_business'])->toBe([
        'id' => '01930000-0000-7000-8000-0000000000b1',
        'name' => 'Estudio Peñalver',
    ])
        ->and($payload['owned_business']['id'])->toBeString()
        ->and($payload['upcoming_appointments_count'])->toBe(4);
});

it('serializes the blocker by its stable value', function () {
    expect(serializedDeletionPreview(deletionPreview(blockedBy: DeletionBlocker::UpcomingAppointments))['data']['blocked_by'])
        ->toBe('upcoming_appointments');
});

it('serializes an account confirmed by email as holding no password', function () {
    expect(serializedDeletionPreview(deletionPreview(hasPassword: false))['data']['has_password'])->toBeFalse();
});

it('serializes the grace period end as DATE_ATOM, keeping the offset it was given', function () {
    expect(serializedDeletionPreview(deletionPreview(gracePeriodEndsAt: '2026-04-28T01:30:00+00:00'))['data']['grace_period_ends_at'])
        ->toBe('2026-04-28T01:30:00+00:00');
});

it('exposes exactly the keys the client transcribed, for an owner too', function () {
    expect(array_keys(serializedDeletionPreview(deletionPreview(ownedBusiness: AccountDeletionFixtures::ownedBusiness()))['data']))
        ->toBe(['email', 'has_password', 'owned_business', 'upcoming_appointments_count', 'blocked_by', 'grace_period_ends_at']);
});
