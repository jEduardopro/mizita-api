<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Contracts\SecondFactorVerifier;
use App\Domains\Accounts\Exceptions\InvalidRecoveryCode;
use App\Domains\Accounts\Exceptions\InvalidTwoFactorCode;
use App\Domains\Accounts\ValueObjects\SecondFactorProof;
use App\Domains\Accounts\ValueObjects\SecondFactorProofKind;
use Throwable;

final class FakeSecondFactorVerifier implements SecondFactorVerifier
{
    public const VALID_CODE = '123456';

    public const VALID_RECOVERY_CODE = 'ABCDE12345-FGHIJ67890';

    /** @var list<array{accountId: string, proof: SecondFactorProof}> */
    public array $verifications = [];

    private ?Throwable $breakdown = null;

    public function breakWith(Throwable $breakdown): void
    {
        $this->breakdown = $breakdown;
    }

    public function verify(string $accountId, SecondFactorProof $proof): void
    {
        $this->verifications[] = ['accountId' => $accountId, 'proof' => $proof];

        if ($this->breakdown !== null) {
            throw $this->breakdown;
        }

        if ($this->accepts($proof)) {
            return;
        }

        throw match ($proof->kind()) {
            SecondFactorProofKind::Totp => InvalidTwoFactorCode::forAccount($accountId),
            SecondFactorProofKind::RecoveryCode => InvalidRecoveryCode::forAccount($accountId),
        };
    }

    private function accepts(SecondFactorProof $proof): bool
    {
        return match ($proof->kind()) {
            SecondFactorProofKind::Totp => $proof->value() === self::VALID_CODE,
            SecondFactorProofKind::RecoveryCode => $proof->value() === self::VALID_RECOVERY_CODE,
        };
    }
}
