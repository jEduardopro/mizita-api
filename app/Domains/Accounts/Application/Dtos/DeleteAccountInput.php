<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\Exceptions\AccountDeletionEmailMismatch;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\Exceptions\IncorrectAccountPassword;

final readonly class DeleteAccountInput
{
    public const MAXIMUM_CONFIRMATION_LENGTH = 255;

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $accountId,
        public ?string $password,
        public ?string $email,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $accountId): self
    {
        return new self(
            accountId: $accountId,
            password: self::textOrNull($payload['password'] ?? null),
            email: self::textOrNull($payload['email'] ?? null),
        );
    }

    /**
     * @throws AccountNotFound
     * @throws IncorrectAccountPassword
     * @throws AccountDeletionEmailMismatch
     */
    public function validate(): void
    {
        $this->validateAccountId();
        $this->validatePassword();
        $this->validateEmail();
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function validateAccountId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->accountId) !== 1) {
            throw AccountNotFound::withId($this->accountId);
        }
    }

    private function validatePassword(): void
    {
        if ($this->password !== null && mb_strlen($this->password) > self::MAXIMUM_CONFIRMATION_LENGTH) {
            throw IncorrectAccountPassword::forAccount($this->accountId);
        }
    }

    private function validateEmail(): void
    {
        if ($this->email !== null && mb_strlen($this->email) > self::MAXIMUM_CONFIRMATION_LENGTH) {
            throw AccountDeletionEmailMismatch::forAccount($this->accountId);
        }
    }
}
