<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\RemoveTeamMemberInput;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use Tests\Support\Staff\StaffFixtures;

it('returns silently for a member uuid, in either case', function (string $staffMemberId) {
    expect(fn () => (new RemoveTeamMemberInput($staffMemberId))->validate())->not->toThrow(Throwable::class);
})->with([
    'lowercase' => StaffFixtures::MEMBER_ID,
    'uppercase' => strtoupper(StaffFixtures::MEMBER_ID),
]);

it('refuses a member that is not a uuid as a member that is not there', function (string $staffMemberId) {
    expect(fn () => (new RemoveTeamMemberInput($staffMemberId))->validate())->toThrow(StaffMemberNotFound::class);
})->with([
    'empty' => '',
    'an int id' => '42',
    'garbage' => 'not-a-uuid',
    'a trailing newline' => StaffFixtures::MEMBER_ID."\n",
]);
