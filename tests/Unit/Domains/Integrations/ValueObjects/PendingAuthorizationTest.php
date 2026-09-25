<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\CalendarAuthorizationStateInvalid;
use App\Domains\Integrations\ValueObjects\PendingAuthorization;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->accountId = '01930000-0000-7000-8000-0000000000a1';

    $this->pending = new PendingAuthorization(
        accountId: $this->accountId,
        businessId: FakeBusinessContext::BUSINESS_ID,
        staffMemberId: '01930000-0000-7000-8000-0000000000d1',
    );
});

it('lets the account it was issued to complete it', function () {
    expect(fn () => $this->pending->assertIssuedTo($this->accountId))->not->toThrow(Throwable::class);
});

it('refuses any other account', function (string $accountId) {
    expect(fn () => $this->pending->assertIssuedTo($accountId))
        ->toThrow(CalendarAuthorizationStateInvalid::class, 'The calendar authorization state was issued to another account.');
})->with([
    'another account' => '01930000-0000-7000-8000-0000000000a2',
    'an empty id' => '',
    'the uppercase spelling of the same id' => '01930000-0000-7000-8000-0000000000A1',
    'the same id with whitespace around it' => ' 01930000-0000-7000-8000-0000000000a1 ',
]);

it('refuses with an invalid failure the transport can classify', function () {
    $failure = null;

    try {
        $this->pending->assertIssuedTo('01930000-0000-7000-8000-0000000000a2');
    } catch (CalendarAuthorizationStateInvalid $refused) {
        $failure = $refused;
    }

    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure?->errorCode())->toBe('calendar_authorization_state_invalid')
        ->and($failure?->kind())->toBe(DomainFailureKind::Invalid);
});
