<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Console;

use App\Domains\Platform\Application\Dtos\RegisterPlatformAdminInput;
use App\Domains\Platform\Application\UseCases\RegisterPlatformAdmin;
use App\Domains\Platform\Exceptions\InvalidPlatformTwoFactorCode;
use App\Http\Responses\FailurePayload;
use App\Shared\Application\UseCaseError;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

final class CreatePlatformAdminCommand extends Command
{
    private const CONFIRMATION_ATTEMPTS = 3;

    private const ISSUER_CONFIG_KEY = 'app.name';

    protected $signature = 'platform:create-admin
        {email : The email the platform admin signs in with}
        {--name= : The name shown for the platform admin}';

    protected $description = 'Create a Mizita platform admin with a password and a confirmed authenticator app';

    public function handle(
        RegisterPlatformAdmin $registerPlatformAdmin,
        TwoFactorAuthenticationProvider $authenticatorApp,
        Repository $config,
    ): int {
        $email = (string) $this->argument('email');
        $name = $this->adminName();
        $password = $this->confirmedPassword();

        if ($password === null) {
            return self::FAILURE;
        }

        $secret = $authenticatorApp->generateSecretKey();

        $this->line('Add this account to your authenticator app:');
        $this->line($authenticatorApp->qrCodeUrl((string) $config->get(self::ISSUER_CONFIG_KEY), $email, $secret));
        $this->line('Secret: '.$secret);

        for ($attempt = 1; $attempt <= self::CONFIRMATION_ATTEMPTS; $attempt++) {
            $response = $registerPlatformAdmin->handle(new RegisterPlatformAdminInput(
                email: $email,
                name: $name,
                password: $password,
                twoFactorSecret: $secret,
                confirmationCode: trim((string) $this->ask('Enter the 6-digit code your authenticator app shows')),
            ));

            if ($response->succeeded()) {
                $this->info(sprintf('Platform admin [%s] created.', $response->value()->email));

                return self::SUCCESS;
            }

            $this->error($this->messageFor($response->error()));

            if (! $response->error()->cause() instanceof InvalidPlatformTwoFactorCode) {
                return self::FAILURE;
            }
        }

        return self::FAILURE;
    }

    private function adminName(): string
    {
        $name = $this->option('name');

        if (is_string($name) && trim($name) !== '') {
            return $name;
        }

        return (string) $this->ask('Name');
    }

    private function confirmedPassword(): ?string
    {
        $password = (string) $this->secret('Password (at least '.RegisterPlatformAdminInput::MINIMUM_PASSWORD_LENGTH.' characters)');

        if (mb_strlen($password) < RegisterPlatformAdminInput::MINIMUM_PASSWORD_LENGTH) {
            $this->error(sprintf('The password must be at least %d characters long.', RegisterPlatformAdminInput::MINIMUM_PASSWORD_LENGTH));

            return null;
        }

        if ($password !== (string) $this->secret('Confirm the password')) {
            $this->error('The passwords do not match.');

            return null;
        }

        return $password;
    }

    private function messageFor(UseCaseError $error): string
    {
        return FailurePayload::messageFor($error->code);
    }
}
