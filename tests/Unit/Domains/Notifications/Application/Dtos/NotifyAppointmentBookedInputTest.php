<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\NotifyAppointmentBookedInput;
use App\Domains\Notifications\Exceptions\NotifiedAppointmentNotFound;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

it('accepts an appointment uuid', function () {
    expect(fn () => (new NotifyAppointmentBookedInput(NotificationsFixtures::APPOINTMENT_ID))->validate())
        ->not->toThrow(Throwable::class);
});

it('treats an appointment identifier that is no uuid as an appointment that does not exist', function (string $appointmentId) {
    expect(fn () => (new NotifyAppointmentBookedInput($appointmentId))->validate())
        ->toThrow(NotifiedAppointmentNotFound::class);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'a sequential int' => '42',
    'a word' => 'not-a-uuid',
    'a uuid with a trailing newline' => NotificationsFixtures::APPOINTMENT_ID."\n",
]);
