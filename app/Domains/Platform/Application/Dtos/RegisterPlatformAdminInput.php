<?php

declare(strict_types=1);

namespace App\Domains\Platform\Application\Dtos;

use App\Domains\Platform\Entities\PlatformAdmin;
use App\Domains\Platform\Exceptions\InvalidPlatformAdminEmail;
use App\Domains\Platform\Exceptions\InvalidPlatformAdminName;
use App\Domains\Platform\Exceptions\InvalidPlatformTwoFactorCode;
use App\Domains\Platform\Exceptions\PlatformAdminPasswordTooShort;
use App\Domains\Platform\ValueObjects\PlatformAdminEmail;

final readonly class RegisterPlatformAdminInput
{
    public const MINIMUM_PASSWORD_LENGTH = 12;

    private const CONFIRMATION_CODE_PATTERN = '/^\d{6}$/D';

    public function __construct(
        public string $email,
        public string $name,
        public string $password,
        public string $twoFactorSecret,
        public string $confirmationCode,
    ) {}

    /**
     * @throws InvalidPlatformAdminEmail
     * @throws InvalidPlatformAdminName
     * @throws PlatformAdminPasswordTooShort
     * @throws InvalidPlatformTwoFactorCode
     */
    public function validate(): void
    {
        $this->validateEmail();
        $this->validateName();
        $this->validatePassword();
        $this->validateConfirmationCode();
    }

    private function validateEmail(): void
    {
        PlatformAdminEmail::fromString($this->email);
    }

    private function validateName(): void
    {
        $name = trim($this->name);

        if ($name === '') {
            throw InvalidPlatformAdminName::empty();
        }

        if (mb_strlen($name) > PlatformAdmin::MAXIMUM_NAME_LENGTH) {
            throw InvalidPlatformAdminName::tooLong(PlatformAdmin::MAXIMUM_NAME_LENGTH);
        }
    }

    private function validatePassword(): void
    {
        if (mb_strlen($this->password) < self::MINIMUM_PASSWORD_LENGTH) {
            throw PlatformAdminPasswordTooShort::below(self::MINIMUM_PASSWORD_LENGTH);
        }
    }

    private function validateConfirmationCode(): void
    {
        if (preg_match(self::CONFIRMATION_CODE_PATTERN, $this->confirmationCode) !== 1) {
            throw InvalidPlatformTwoFactorCode::malformed();
        }
    }
}
