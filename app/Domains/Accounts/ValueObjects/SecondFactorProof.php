<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

final readonly class SecondFactorProof
{
    private function __construct(
        private SecondFactorProofKind $kind,
        private string $value,
    ) {}

    public static function totp(string $code): self
    {
        return new self(SecondFactorProofKind::Totp, trim($code));
    }

    public static function recoveryCode(string $code): self
    {
        return new self(SecondFactorProofKind::RecoveryCode, trim($code));
    }

    public function kind(): SecondFactorProofKind
    {
        return $this->kind;
    }

    public function value(): string
    {
        return $this->value;
    }
}
