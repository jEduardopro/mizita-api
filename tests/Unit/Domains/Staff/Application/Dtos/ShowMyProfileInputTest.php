<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\ShowMyProfileInput;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use Tests\Support\Staff\StaffFixtures;

it('returns silently for an account uuid, in either case', function (string $accountId) {
    expect(fn () => (new ShowMyProfileInput($accountId))->validate())->not->toThrow(Throwable::class);
})->with([
    'lowercase' => StaffFixtures::ACCOUNT_ID,
    'uppercase' => strtoupper(StaffFixtures::ACCOUNT_ID),
]);

it('refuses an account that is not a uuid as a member that is not there', function (string $accountId) {
    expect(fn () => (new ShowMyProfileInput($accountId))->validate())->toThrow(StaffMemberNotFound::class);
})->with([
    'empty' => '',
    'an int id' => '42',
    'garbage' => 'not-a-uuid',
    'a trailing newline' => StaffFixtures::ACCOUNT_ID."\n",
]);
