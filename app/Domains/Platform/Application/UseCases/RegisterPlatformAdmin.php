<?php

declare(strict_types=1);

namespace App\Domains\Platform\Application\UseCases;

use App\Domains\Platform\Application\Dtos\PlatformAdminData;
use App\Domains\Platform\Application\Dtos\RegisterPlatformAdminInput;
use App\Domains\Platform\Contracts\AuthenticatorApp;
use App\Domains\Platform\Contracts\PasswordHasher;
use App\Domains\Platform\Contracts\PlatformAdminRepository;
use App\Domains\Platform\Entities\PlatformAdmin;
use App\Domains\Platform\Exceptions\InvalidPlatformTwoFactorCode;
use App\Domains\Platform\Exceptions\PlatformAdminAlreadyExists;
use App\Domains\Platform\ValueObjects\PlatformAdminEmail;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;

final class RegisterPlatformAdmin
{
    public function __construct(
        private readonly PlatformAdminRepository $admins,
        private readonly PasswordHasher $passwords,
        private readonly AuthenticatorApp $authenticatorApp,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<PlatformAdminData>
     */
    public function handle(RegisterPlatformAdminInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $admin = $this->register($input, PlatformAdminEmail::fromString($input->email));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success(PlatformAdminData::fromEntity($admin));
    }

    /**
     * @throws PlatformAdminAlreadyExists
     * @throws InvalidPlatformTwoFactorCode
     */
    private function register(RegisterPlatformAdminInput $input, PlatformAdminEmail $email): PlatformAdmin
    {
        if ($this->admins->existsByEmail($email)) {
            throw PlatformAdminAlreadyExists::withEmail($email->value);
        }

        if (! $this->authenticatorApp->confirms($input->twoFactorSecret, $input->confirmationCode)) {
            throw InvalidPlatformTwoFactorCode::notConfirmedBy($email->value);
        }

        $admin = PlatformAdmin::registerWithConfirmedAuthenticator(
            id: $this->ids->next(),
            name: $input->name,
            email: $email,
            passwordHash: $this->passwords->hash($input->password),
            twoFactorSecret: $input->twoFactorSecret,
            now: $this->clock->now(),
        );

        $this->admins->save($admin);

        return $admin;
    }
}
