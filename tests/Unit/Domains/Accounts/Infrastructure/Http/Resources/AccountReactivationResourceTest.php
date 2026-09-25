<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\AccountReactivationData;
use App\Domains\Accounts\Infrastructure\Http\Resources\AccountReactivationResource;
use App\Domains\Accounts\ValueObjects\ClosedBusinessSnapshot;
use Tests\Support\Accounts\AccountDeletionFixtures;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function serializedReactivation(?ClosedBusinessSnapshot $business = null): array
{
    return AccountReactivationResource::make(
        AccountReactivationData::of(AccountDeletionFixtures::scheduledAccount(), $business),
    )->response()->getData(true);
}

it('serializes the reactivation of an account that owned no business, wrapped in data', function () {
    expect(serializedReactivation())->toBe(['data' => [
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
        'deletion_requested_at' => '2025-12-20T09:15:00+00:00',
        'grace_period_ends_at' => '2026-01-19T09:15:00+00:00',
        'business' => null,
    ]]);
});

it('serializes the closed business with its dates as DATE_ATOM', function () {
    expect(serializedReactivation(AccountDeletionFixtures::closedBusiness())['data']['business'])->toBe([
        'name' => 'Estudio Peñalver',
        'closed_at' => '2025-12-20T09:15:00+00:00',
        'purge_scheduled_at' => '2026-01-19T09:15:00+00:00',
        'purged' => false,
    ]);
});

it('serializes a purged business as purged', function () {
    expect(serializedReactivation(AccountDeletionFixtures::closedBusiness(purged: true))['data']['business']['purged'])->toBeTrue();
});

it('never serializes an identifier, neither the account nor the business one', function () {
    $payload = serializedReactivation(AccountDeletionFixtures::closedBusiness())['data'];

    expect($payload)->not->toHaveKey('id')
        ->and($payload['business'])->not->toHaveKey('id')
        ->and($payload['business'])->not->toHaveKey('business_id');
});

it('exposes exactly the keys the client transcribed', function () {
    expect(array_keys(serializedReactivation(AccountDeletionFixtures::closedBusiness())['data']))
        ->toBe(['email', 'name', 'deletion_requested_at', 'grace_period_ends_at', 'business']);
});
