<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\TwoFactor;

use App\Domains\Accounts\Contracts\SecondFactorVerifier;
use App\Domains\Accounts\Exceptions\InvalidRecoveryCode;
use App\Domains\Accounts\Exceptions\InvalidTwoFactorCode;
use App\Domains\Accounts\ValueObjects\SecondFactorProof;
use App\Domains\Accounts\ValueObjects\SecondFactorProofKind;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

final class FortifySecondFactorVerifier implements SecondFactorVerifier
{
    private const NO_CODE = '';

    public function __construct(
        private readonly TwoFactorAuthenticationProvider $authenticatorApp,
    ) {}

    public function verify(string $accountId, SecondFactorProof $proof): void
    {
        match ($proof->kind()) {
            SecondFactorProofKind::Totp => $this->verifyTotp($accountId, $proof->value()),
            SecondFactorProofKind::RecoveryCode => $this->consumeRecoveryCode($accountId, $proof->value()),
        };
    }

    /**
     * @throws InvalidTwoFactorCode
     */
    private function verifyTotp(string $accountId, string $code): void
    {
        $account = $this->enrolledAccount($accountId)->first();

        if (! $account instanceof User || $code === self::NO_CODE) {
            throw InvalidTwoFactorCode::forAccount($accountId);
        }

        $secret = Fortify::currentEncrypter()->decrypt($account->two_factor_secret);

        if (! $this->authenticatorApp->verify($secret, $code)) {
            throw InvalidTwoFactorCode::forAccount($accountId);
        }
    }

    /**
     * @throws InvalidRecoveryCode
     */
    private function consumeRecoveryCode(string $accountId, string $code): void
    {
        DB::transaction(function () use ($accountId, $code): void {
            $account = $this->enrolledAccount($accountId)->lockForUpdate()->first();

            if (! $account instanceof User) {
                throw InvalidRecoveryCode::forAccount($accountId);
            }

            $matchingCode = $this->matchingRecoveryCode($account, $code)
                ?? throw InvalidRecoveryCode::forAccount($accountId);

            $account->replaceRecoveryCode($matchingCode);
        });
    }

    private function matchingRecoveryCode(User $account, string $code): ?string
    {
        if ($code === self::NO_CODE || $account->two_factor_recovery_codes === null) {
            return null;
        }

        foreach ($account->recoveryCodes() as $recoveryCode) {
            if (is_string($recoveryCode) && hash_equals($recoveryCode, $code)) {
                return $recoveryCode;
            }
        }

        return null;
    }

    /**
     * @return Builder<User>
     */
    private function enrolledAccount(string $accountId): Builder
    {
        return User::withTrashed()
            ->where('uuid', $accountId)
            ->whereNotNull('two_factor_secret')
            ->whereNotNull('two_factor_confirmed_at');
    }
}
