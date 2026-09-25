<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Entities;

use App\Domains\Accounts\Exceptions\AccountAlreadyScheduledForDeletion;
use App\Domains\Accounts\Exceptions\AccountDeletionEmailMismatch;
use App\Domains\Accounts\Exceptions\AccountHoldsOwnPassword;
use App\Domains\Accounts\Exceptions\AccountNotScheduledForDeletion;
use App\Domains\Accounts\Exceptions\AccountPendingReactivation;
use App\Domains\Accounts\Exceptions\AccountSignsInWithSocialIdentity;
use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidAccountName;
use App\Domains\Accounts\ValueObjects\DeletionGracePeriod;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Domains\Accounts\ValueObjects\TwoFactorStatus;
use DateTimeImmutable;

final class Account
{
    public const MAXIMUM_NAME_LENGTH = 255;

    /**
     * @param  list<SocialProvider>  $linkedSocialProviders
     */
    private function __construct(
        public readonly string $id,
        private string $name,
        private string $email,
        private ?DateTimeImmutable $emailVerifiedAt,
        public readonly DateTimeImmutable $createdAt,
        private PasswordStatus $passwordStatus,
        private ?string $issuedPasswordHash,
        private readonly array $linkedSocialProviders,
        private ?DateTimeImmutable $deletionRequestedAt,
        private readonly TwoFactorStatus $twoFactorStatus,
    ) {}

    public static function registerWithVerifiedEmail(
        string $id,
        string $name,
        string $email,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            name: self::normalizeName($name),
            email: self::normalizeEmail($email),
            emailVerifiedAt: $now,
            createdAt: $now,
            passwordStatus: PasswordStatus::Absent,
            issuedPasswordHash: null,
            linkedSocialProviders: [],
            deletionRequestedAt: null,
            twoFactorStatus: TwoFactorStatus::Disabled,
        );
    }

    public static function inviteWithTemporaryPassword(
        string $id,
        string $name,
        string $email,
        string $temporaryPasswordHash,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            name: self::normalizeName($name),
            email: self::normalizeEmail($email),
            emailVerifiedAt: null,
            createdAt: $now,
            passwordStatus: PasswordStatus::Temporary,
            issuedPasswordHash: $temporaryPasswordHash,
            linkedSocialProviders: [],
            deletionRequestedAt: null,
            twoFactorStatus: TwoFactorStatus::Disabled,
        );
    }

    public static function inviteWithoutPassword(
        string $id,
        string $name,
        string $email,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            name: self::normalizeName($name),
            email: self::normalizeEmail($email),
            emailVerifiedAt: null,
            createdAt: $now,
            passwordStatus: PasswordStatus::Absent,
            issuedPasswordHash: null,
            linkedSocialProviders: [],
            deletionRequestedAt: null,
            twoFactorStatus: TwoFactorStatus::Disabled,
        );
    }

    /**
     * @param  list<SocialProvider>  $linkedSocialProviders
     */
    public static function restore(
        string $id,
        string $name,
        string $email,
        ?DateTimeImmutable $emailVerifiedAt,
        DateTimeImmutable $createdAt,
        PasswordStatus $passwordStatus = PasswordStatus::Absent,
        array $linkedSocialProviders = [],
        ?DateTimeImmutable $deletionRequestedAt = null,
        TwoFactorStatus $twoFactorStatus = TwoFactorStatus::Disabled,
    ): self {
        return new self(
            id: $id,
            name: $name,
            email: $email,
            emailVerifiedAt: $emailVerifiedAt,
            createdAt: $createdAt,
            passwordStatus: $passwordStatus,
            issuedPasswordHash: null,
            linkedSocialProviders: $linkedSocialProviders,
            deletionRequestedAt: $deletionRequestedAt,
            twoFactorStatus: $twoFactorStatus,
        );
    }

    public function verifyEmail(DateTimeImmutable $now): void
    {
        if ($this->emailVerifiedAt !== null) {
            return;
        }

        $this->emailVerifiedAt = $now;
    }

    public function rename(string $name): void
    {
        $this->name = self::normalizeName($name);
    }

    public function acceptsTemporaryPassword(): bool
    {
        return $this->passwordStatus->acceptsTemporaryPassword() && ! $this->signsInWithSocialIdentity();
    }

    /**
     * @throws AccountHoldsOwnPassword
     * @throws AccountSignsInWithSocialIdentity
     */
    public function issueTemporaryPassword(string $temporaryPasswordHash): void
    {
        if (! $this->passwordStatus->acceptsTemporaryPassword()) {
            throw AccountHoldsOwnPassword::forAccount($this->id);
        }

        if ($this->signsInWithSocialIdentity()) {
            throw AccountSignsInWithSocialIdentity::forAccount($this->id);
        }

        $this->passwordStatus = PasswordStatus::Temporary;
        $this->issuedPasswordHash = $temporaryPasswordHash;
    }

    /**
     * @throws AccountAlreadyScheduledForDeletion
     */
    public function scheduleDeletion(DateTimeImmutable $now): void
    {
        if ($this->isScheduledForDeletion()) {
            throw AccountAlreadyScheduledForDeletion::forAccount($this->id);
        }

        $this->deletionRequestedAt = $now;
    }

    /**
     * @throws AccountNotScheduledForDeletion
     */
    public function reactivate(): void
    {
        $this->ensureScheduledForDeletion();

        $this->deletionRequestedAt = null;
    }

    /**
     * @throws AccountPendingReactivation
     */
    public function ensureActive(): void
    {
        if ($this->isScheduledForDeletion()) {
            throw AccountPendingReactivation::forAccount($this->id);
        }
    }

    /**
     * @throws AccountNotScheduledForDeletion
     */
    public function ensureScheduledForDeletion(): void
    {
        if (! $this->isScheduledForDeletion()) {
            throw AccountNotScheduledForDeletion::forAccount($this->id);
        }
    }

    /**
     * @throws AccountDeletionEmailMismatch
     */
    public function ensureDeletionConfirmedBy(string $typedEmail): void
    {
        if (mb_strtolower(trim($typedEmail)) !== mb_strtolower($this->email)) {
            throw AccountDeletionEmailMismatch::forAccount($this->id);
        }
    }

    public function holdsPassword(): bool
    {
        return $this->passwordStatus !== PasswordStatus::Absent;
    }

    public function isScheduledForDeletion(): bool
    {
        return $this->deletionRequestedAt !== null;
    }

    public function deletionRequestedAt(): ?DateTimeImmutable
    {
        return $this->deletionRequestedAt;
    }

    public function gracePeriodEndsAt(): ?DateTimeImmutable
    {
        if ($this->deletionRequestedAt === null) {
            return null;
        }

        return DeletionGracePeriod::endingFrom($this->deletionRequestedAt);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function twoFactorStatus(): TwoFactorStatus
    {
        return $this->twoFactorStatus;
    }

    public function requiresSecondFactor(): bool
    {
        return $this->twoFactorStatus->requiresSecondFactor();
    }

    public function mustChangePassword(): bool
    {
        return $this->passwordStatus === PasswordStatus::Temporary;
    }

    public function issuedPasswordHash(): ?string
    {
        return $this->issuedPasswordHash;
    }

    private function signsInWithSocialIdentity(): bool
    {
        return $this->linkedSocialProviders !== [];
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw InvalidAccountName::empty();
        }

        if (mb_strlen($name) > self::MAXIMUM_NAME_LENGTH) {
            throw InvalidAccountName::tooLong(self::MAXIMUM_NAME_LENGTH);
        }

        return $name;
    }

    private static function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            throw InvalidAccountEmail::empty();
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidAccountEmail::malformed($email);
        }

        return $email;
    }
}
