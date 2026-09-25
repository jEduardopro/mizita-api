<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Notifications\TemporaryPasswordInvitation;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\Support\Staff\StaffFixtures;
use Tests\TestCase;

uses(TestCase::class);

function temporaryPasswordMail(string $locale): MailMessage
{
    app()->setLocale($locale);

    return (new TemporaryPasswordInvitation(
        businessName: StaffFixtures::BUSINESS_NAME,
        email: 'grace@example.com',
        temporaryPassword: StaffFixtures::TEMPORARY_PASSWORD,
    ))->toMail(new stdClass);
}

it('writes the invitation in english with the business, the email and the temporary password', function () {
    $mail = temporaryPasswordMail('en');

    expect($mail->subject)->toBe('You have been invited to join Barbería Ñuñoa on Mizita')
        ->and($mail->greeting)->toBe('Hello!')
        ->and($mail->introLines)->toBe([
            'Barbería Ñuñoa has added you to their team on Mizita. Use the details below to sign in for the first time.',
            'Email: grace@example.com',
            'Temporary password: `Tmp-Pa55word!`',
        ])
        ->and($mail->actionText)->toBe('Sign in')
        ->and($mail->actionUrl)->toBe(route('login'))
        ->and($mail->outroLines)->toBe([
            'You will be asked to choose your own password as soon as you sign in. If you were not expecting this invitation, you can ignore this email.',
        ]);
});

it('writes the invitation in spanish with the business, the email and the temporary password', function () {
    $mail = temporaryPasswordMail('es');

    expect($mail->subject)->toBe('Te han invitado a unirte a Barbería Ñuñoa en Mizita')
        ->and($mail->greeting)->toBe('¡Hola!')
        ->and($mail->introLines)->toBe([
            'Barbería Ñuñoa te ha añadido a su equipo en Mizita. Usa estos datos para iniciar sesión por primera vez.',
            'Correo electrónico: grace@example.com',
            'Contraseña temporal: `Tmp-Pa55word!`',
        ])
        ->and($mail->actionText)->toBe('Iniciar sesión')
        ->and($mail->actionUrl)->toBe(route('login'))
        ->and($mail->outroLines)->toBe([
            'En cuanto inicies sesión te pediremos que elijas tu propia contraseña. Si no esperabas esta invitación, puedes ignorar este correo.',
        ]);
});

it('keeps the temporary password out of the subject, which mail clients show everywhere', function (string $locale) {
    expect(temporaryPasswordMail($locale)->subject)->not->toContain(StaffFixtures::TEMPORARY_PASSWORD);
})->with(['en', 'es']);

it('is sent by mail only', function () {
    $notification = new TemporaryPasswordInvitation(StaffFixtures::BUSINESS_NAME, 'grace@example.com', StaffFixtures::TEMPORARY_PASSWORD);

    expect($notification->via(new stdClass))->toBe(['mail']);
});

it('is queued encrypted, so the password never sits in the jobs table in clear text', function () {
    $notification = new TemporaryPasswordInvitation(StaffFixtures::BUSINESS_NAME, 'grace@example.com', StaffFixtures::TEMPORARY_PASSWORD);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification)->toBeInstanceOf(ShouldBeEncrypted::class);
});

it('waits for the commit, so no invitation leaves for a membership that rolled back', function () {
    $notification = new TemporaryPasswordInvitation(StaffFixtures::BUSINESS_NAME, 'grace@example.com', StaffFixtures::TEMPORARY_PASSWORD);

    expect($notification->afterCommit)->toBeTrue();
});
