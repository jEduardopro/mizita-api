<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidAccountName;

final readonly class ProvisionInvitedAccountInput
{
    private const MAXIMUM_EMAIL_LENGTH = 255;

    public string $name;

    public string $email;

    public function __construct(string $name, string $email)
    {
        $this->name = trim($name);
        $this->email = mb_strtolower(trim($email));
    }

    /**
     * @throws InvalidAccountName
     * @throws InvalidAccountEmail
     */
    public function validate(): void
    {
        $this->validateName();
        $this->validateEmail();
    }

    private function validateName(): void
    {
        if ($this->name === '') {
            throw InvalidAccountName::empty();
        }

        if (mb_strlen($this->name) > Account::MAXIMUM_NAME_LENGTH) {
            throw InvalidAccountName::tooLong(Account::MAXIMUM_NAME_LENGTH);
        }
    }

    private function validateEmail(): void
    {
        if ($this->email === '') {
            throw InvalidAccountEmail::empty();
        }

        if (mb_strlen($this->email) > self::MAXIMUM_EMAIL_LENGTH) {
            throw InvalidAccountEmail::tooLong(self::MAXIMUM_EMAIL_LENGTH);
        }

        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidAccountEmail::malformed($this->email);
        }
    }
}
