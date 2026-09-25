<?php

declare(strict_types=1);

use App\Domains\Accounts\Contracts\TemporaryPasswordVault;
use App\Domains\Accounts\Infrastructure\Passwords\EncryptedTemporaryPasswordVault;
use App\Domains\Staff\Application\Dtos\RevealedTemporaryPassword;
use App\Domains\Staff\Application\Dtos\SendTeamInvitationInput;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Events\TeamMemberInvited;
use App\Domains\Staff\Infrastructure\Notifications\TemporaryPasswordInvitation;
use App\Domains\Staff\ValueObjects\ProvisionedAccount;
use App\Domains\Staff\ValueObjects\TeamInvitation;

it('redacts the temporary password from every stack trace that passes through it', function (string $class, string $method) {
    $parameter = new ReflectionParameter([$class, $method], 'temporaryPassword');

    expect($parameter->getAttributes(SensitiveParameter::class))->toHaveCount(1);
})->with([
    'the provisioned account' => [ProvisionedAccount::class, '__construct'],
    'the invitation handed to the mailer' => [TeamInvitation::class, '__construct'],
    'the invitation event' => [TeamMemberInvited::class, '__construct'],
    'the input of the use case that mails it' => [SendTeamInvitationInput::class, '__construct'],
    'the entity building the event' => [StaffMember::class, 'invitationFor'],
    'the notification carrying it' => [TemporaryPasswordInvitation::class, '__construct'],
    'the vault port keeping it' => [TemporaryPasswordVault::class, 'keep'],
    'the encrypted vault keeping it' => [EncryptedTemporaryPasswordVault::class, 'keep'],
    'the password revealed to the owner' => [RevealedTemporaryPassword::class, '__construct'],
]);
