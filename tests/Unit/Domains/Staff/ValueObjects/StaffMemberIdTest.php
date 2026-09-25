<?php

declare(strict_types=1);

use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffMemberId;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Staff\StaffFixtures;

it('keeps a uuid exactly as it was given', function (string $uuid) {
    expect(StaffMemberId::fromString($uuid)->value)->toBe($uuid);
})->with([
    'lowercase' => StaffFixtures::MEMBER_ID,
    'uppercase' => strtoupper(StaffFixtures::MEMBER_ID),
]);

it('answers a value that is not a uuid as a member nobody has, never as a malformed request', function (string $value) {
    $thrown = null;

    try {
        StaffMemberId::fromString($value);
    } catch (StaffMemberNotFound $refusal) {
        $thrown = $refusal;
    }

    expect($thrown)->toBeInstanceOf(DomainFailure::class)
        ->and($thrown?->errorCode())->toBe('staff_member_not_found')
        ->and($thrown?->getMessage())->toBe("Staff member [{$value}] was not found.");
})->with([
    'empty' => '',
    'a sequential key' => '42',
    'garbage' => 'not-a-uuid',
    'no hyphens' => str_replace('-', '', StaffFixtures::MEMBER_ID),
    'padded' => ' '.StaffFixtures::MEMBER_ID.' ',
    'a trailing newline' => StaffFixtures::MEMBER_ID."\n",
    'one character short' => substr(StaffFixtures::MEMBER_ID, 0, -1),
    'a non hex digit' => '01930000-0000-7000-8000-0000000000g1',
]);
