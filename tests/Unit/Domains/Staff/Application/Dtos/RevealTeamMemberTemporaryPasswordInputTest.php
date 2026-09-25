<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\RevealTeamMemberTemporaryPasswordInput;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Staff\StaffFixtures;

it('returns silently for a member uuid, in either case', function (string $staffMemberId) {
    expect(fn () => (new RevealTeamMemberTemporaryPasswordInput($staffMemberId))->validate())->not->toThrow(Throwable::class);
})->with([
    'lowercase' => StaffFixtures::MEMBER_ID,
    'uppercase' => strtoupper(StaffFixtures::MEMBER_ID),
]);

it('refuses a member that is not a uuid as a member that is not there', function (string $staffMemberId) {
    expect(fn () => (new RevealTeamMemberTemporaryPasswordInput($staffMemberId))->validate())->toThrow(StaffMemberNotFound::class);
})->with([
    'empty' => '',
    'whitespace' => '   ',
    'an int id' => '42',
    'garbage' => 'not-a-uuid',
    'a trailing newline' => StaffFixtures::MEMBER_ID."\n",
]);

it('refuses with a failure the use case can return rather than throw', function () {
    try {
        (new RevealTeamMemberTemporaryPasswordInput('42'))->validate();
    } catch (Throwable $refusal) {
        expect($refusal)->toBeInstanceOf(DomainFailure::class);

        return;
    }

    throw new RuntimeException('The malformed member id was not refused.');
});
