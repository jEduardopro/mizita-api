<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent\Mappers;

use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Models\User;
use DateTimeImmutable;

final class AccountMapper
{
    /**
     * @param  list<SocialProvider>  $linkedSocialProviders
     */
    public function toEntity(User $model, array $linkedSocialProviders): Account
    {
        return Account::restore(
            id: $model->uuid,
            name: $model->name,
            email: $model->email,
            emailVerifiedAt: $model->email_verified_at === null
                ? null
                : DateTimeImmutable::createFromInterface($model->email_verified_at),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            passwordStatus: $this->passwordStatusOf($model),
            linkedSocialProviders: $linkedSocialProviders,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Account $account): array
    {
        $attributes = [
            'uuid' => $account->id,
            'name' => $account->name(),
            'email' => $account->email(),
            'email_verified_at' => $account->emailVerifiedAt(),
        ];

        $issuedPasswordHash = $account->issuedPasswordHash();

        if ($issuedPasswordHash === null) {
            return $attributes;
        }

        return [
            ...$attributes,
            'password' => $issuedPasswordHash,
            'must_change_password' => $account->mustChangePassword(),
        ];
    }

    private function passwordStatusOf(User $model): PasswordStatus
    {
        if ($model->getAuthPassword() === null) {
            return PasswordStatus::Absent;
        }

        if ($model->mustChangePassword()) {
            return PasswordStatus::Temporary;
        }

        return PasswordStatus::Chosen;
    }
}
