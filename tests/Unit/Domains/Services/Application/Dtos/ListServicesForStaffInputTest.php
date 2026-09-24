<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ListServicesForStaffInput;
use App\Domains\Services\Exceptions\UnknownStaffMember;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Services\ServiceFixtures;

it('accepts an identifier a staff member could carry', function (string $staffMemberId) {
    expect(fn () => (new ListServicesForStaffInput($staffMemberId))->validate())->not->toThrow(Throwable::class);
})->with([
    'a uuid' => ServiceFixtures::STAFF_ID,
    'a uuid in uppercase' => '01930000-0000-7000-8000-0000000000D1',
]);

it('refuses an identifier that cannot be a staff member, as unknown', function (string $staffMemberId) {
    expect(fn () => (new ListServicesForStaffInput($staffMemberId))->validate())
        ->toThrow(UnknownStaffMember::class);
})->with([
    'empty' => '',
    'spaces' => '   ',
    'a name' => 'ada-lovelace',
    'an integer key' => '7',
    'a truncated uuid' => '01930000-0000-7000-8000',
    'a uuid with a trailing newline' => ServiceFixtures::STAFF_ID."\n",
    'sql' => "' or 1=1 --",
]);

it('refuses with a failure the use case can return instead of throw', function () {
    $refusal = null;

    try {
        (new ListServicesForStaffInput('not-a-uuid'))->validate();
    } catch (Throwable $thrown) {
        $refusal = $thrown;
    }

    expect($refusal)->toBeInstanceOf(DomainFailure::class);
});
