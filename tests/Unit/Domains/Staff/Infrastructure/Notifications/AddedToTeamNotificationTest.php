<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Notifications\AddedToTeamNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\Support\Staff\StaffFixtures;
use Tests\TestCase;

uses(TestCase::class);

function addedToTeamMail(string $locale): MailMessage
{
    app()->setLocale($locale);

    return (new AddedToTeamNotification(StaffFixtures::BUSINESS_NAME))->toMail(new stdClass);
}

it('tells an existing account in english that it was added, and asks it to sign in as usual', function () {
    $mail = addedToTeamMail('en');

    expect($mail->subject)->toBe('You have been added to Barbería Ñuñoa on Mizita')
        ->and($mail->greeting)->toBe('Hello!')
        ->and($mail->introLines)->toBe(['Barbería Ñuñoa has added you to their team on Mizita.'])
        ->and($mail->actionText)->toBe('Sign in')
        ->and($mail->actionUrl)->toBe(route('login'))
        ->and($mail->outroLines)->toBe(['Sign in with your existing Mizita account to start working with them.']);
});

it('tells an existing account in spanish that it was added, and asks it to sign in as usual', function () {
    $mail = addedToTeamMail('es');

    expect($mail->subject)->toBe('Te han añadido a Barbería Ñuñoa en Mizita')
        ->and($mail->greeting)->toBe('¡Hola!')
        ->and($mail->introLines)->toBe(['Barbería Ñuñoa te ha añadido a su equipo en Mizita.'])
        ->and($mail->actionText)->toBe('Iniciar sesión')
        ->and($mail->outroLines)->toBe(['Inicia sesión con tu cuenta de Mizita de siempre para empezar a trabajar con ellos.']);
});

it('mentions no password at all, because the account already has one', function (string $locale) {
    $mail = addedToTeamMail($locale);
    $lines = implode("\n", [$mail->subject, ...$mail->introLines, ...$mail->outroLines]);

    expect(mb_strtolower($lines))->not->toContain('password')
        ->and(mb_strtolower($lines))->not->toContain('contraseña');
})->with(['en', 'es']);

it('is queued, sent by mail only, after the commit', function () {
    $notification = new AddedToTeamNotification(StaffFixtures::BUSINESS_NAME);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->via(new stdClass))->toBe(['mail'])
        ->and($notification->afterCommit)->toBeTrue();
});
