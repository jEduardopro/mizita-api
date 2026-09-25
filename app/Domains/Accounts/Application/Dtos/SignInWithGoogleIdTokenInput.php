<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;
use App\Domains\Accounts\Exceptions\InvalidRecoveryCode;
use App\Domains\Accounts\Exceptions\InvalidTwoFactorCode;
use App\Domains\Accounts\ValueObjects\SecondFactorProof;

final readonly class SignInWithGoogleIdTokenInput
{
    public const MAXIMUM_CODE_LENGTH = 16;

    public const MAXIMUM_RECOVERY_CODE_LENGTH = 64;

    public function __construct(
        public string $idToken,
        public ?string $code = null,
        public ?string $recoveryCode = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            idToken: self::textOrEmpty($payload['id_token'] ?? null),
            code: self::textOrNull($payload['code'] ?? null),
            recoveryCode: self::textOrNull($payload['recovery_code'] ?? null),
        );
    }

    /**
     * @throws InvalidGoogleIdToken
     * @throws InvalidTwoFactorCode
     * @throws InvalidRecoveryCode
     */
    public function validate(): void
    {
        $this->validateIdToken();
        $this->validateSingleSecondFactor();
        $this->validateCode();
        $this->validateRecoveryCode();
    }

    public function secondFactorProof(): ?SecondFactorProof
    {
        if ($this->code !== null) {
            return SecondFactorProof::totp($this->code);
        }

        if ($this->recoveryCode !== null) {
            return SecondFactorProof::recoveryCode($this->recoveryCode);
        }

        return null;
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function textOrNull(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function validateIdToken(): void
    {
        if (trim($this->idToken) === '') {
            throw InvalidGoogleIdToken::notAJsonWebToken();
        }
    }

    private function validateSingleSecondFactor(): void
    {
        if ($this->code !== null && $this->recoveryCode !== null) {
            throw new InvalidTwoFactorCode('A two factor code and a recovery code cannot be given together.');
        }
    }

    private function validateCode(): void
    {
        if ($this->code !== null && mb_strlen($this->code) > self::MAXIMUM_CODE_LENGTH) {
            throw new InvalidTwoFactorCode('The two factor code exceeds '.self::MAXIMUM_CODE_LENGTH.' characters.');
        }
    }

    private function validateRecoveryCode(): void
    {
        if ($this->recoveryCode !== null && mb_strlen($this->recoveryCode) > self::MAXIMUM_RECOVERY_CODE_LENGTH) {
            throw InvalidRecoveryCode::tooLong(self::MAXIMUM_RECOVERY_CODE_LENGTH);
        }
    }
}
