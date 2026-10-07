<?php

declare(strict_types=1);

use App\Domains\Notifications\ValueObjects\Identifier;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

it('accepts a uuid in either case', function (string $value) {
    expect(Identifier::isWellFormed($value))->toBeTrue();
})->with([
    'lower case' => NotificationsFixtures::NOTIFICATION_ID,
    'upper case' => strtoupper(NotificationsFixtures::NOTIFICATION_ID),
]);

it('refuses anything that is not a uuid', function (string $value) {
    expect(Identifier::isWellFormed($value))->toBeFalse();
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'a sequential int' => '42',
    'a word' => 'not-a-uuid',
    'a uuid without dashes' => str_replace('-', '', NotificationsFixtures::NOTIFICATION_ID),
    'a uuid with a trailing newline' => NotificationsFixtures::NOTIFICATION_ID."\n",
    'a uuid wrapped in spaces' => ' '.NotificationsFixtures::NOTIFICATION_ID.' ',
    'a uuid with a non hex digit' => '01930000-0000-7000-8000-00000000020g',
]);
