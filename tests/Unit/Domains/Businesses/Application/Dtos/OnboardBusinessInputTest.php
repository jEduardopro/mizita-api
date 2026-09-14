<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\OnboardBusinessInput;
use App\Domains\Businesses\Application\Dtos\PhoneNumberInput;
use Tests\Support\Businesses\OnboardingFixtures;

/*
| The DTO builds itself from the validated payload so the controller can take the
| request, hand the array over and return the response - nothing else. That moves
| two decisions here that used to sit in a controller: how the nested phone is
| assembled, and where the owner comes from.
|
| The array, never the Request: Application/ may not import Illuminate\Http, and
| the arch suite fails the moment it does.
*/

/**
 * @return array<string, mixed>
 */
function mizitaOnboardingPayload(array $overrides = []): array
{
    return [
        'name' => OnboardingFixtures::NAME,
        'timezone' => OnboardingFixtures::TIMEZONE,
        'industry_id' => OnboardingFixtures::INDUSTRY_ID,
        ...$overrides,
    ];
}

it('resolves every field the form request lets through', function () {
    $input = OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload(),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    );

    expect($input->name)->toBe(OnboardingFixtures::NAME)
        ->and($input->timezone)->toBe(OnboardingFixtures::TIMEZONE)
        ->and($input->industryId)->toBe(OnboardingFixtures::INDUSTRY_ID)
        ->and($input->ownerAccountId)->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID)
        ->and($input->phone)->toBeNull();
});

it('assembles the nested phone from the two facts the client sent', function () {
    $input = OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload(['phone' => ['country_code' => 'MX', 'national_number' => '5512345678']]),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    );

    expect($input->phone)->toBeInstanceOf(PhoneNumberInput::class)
        ->and($input->phone->countryCode)->toBe('MX')
        ->and($input->phone->nationalNumber)->toBe('5512345678');
});

it('carries the number exactly as it was typed, judging nothing', function () {
    // Separators are the parser's business, and the country is the use case's.
    // A DTO that trimmed or uppercased here would be taking a decision the layer
    // above it is not allowed to take, and the use case would parse a different
    // string from the one the caller sent.
    $input = OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload(['phone' => ['country_code' => 'mx', 'national_number' => ' (55) 1234-5678 ']]),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    );

    expect($input->phone->countryCode)->toBe('mx')
        ->and($input->phone->nationalNumber)->toBe(' (55) 1234-5678 ');
});

it('reads no phone at all when the owner skipped it', function (array $payload) {
    // Null means "skipped", and only that. A number that turns out not to be real
    // is a different answer, given later and by the use case.
    expect(OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload($payload),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    )->phone)->toBeNull();
})->with([
    'the key is absent' => [[]],
    'the key is null' => [['phone' => null]],
    'the object is empty' => [['phone' => []]],
]);

it('takes the owner from its argument and never from the payload', function (array $smuggled) {
    // A client that could name the owner could hand a business to a stranger, so
    // the authenticated caller arrives as a parameter rather than as a key.
    $input = OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload($smuggled),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    );

    expect($input->ownerAccountId)->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID);
})->with([
    'owner_account_id' => [['owner_account_id' => 'someone-elses-account']],
    'ownerAccountId' => [['ownerAccountId' => 'someone-elses-account']],
    'owner' => [['owner' => 'someone-elses-account']],
]);

it('ignores an address the client tried to choose for itself', function () {
    // The slug is derived from the name, so accepting one would be accepting a
    // second and contradictory source for it.
    $input = OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload(['slug' => 'a-slug-i-picked']),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    );

    expect($input)->toEqual(OnboardBusinessInput::fromRequest(
        mizitaOnboardingPayload(),
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    ));
});
