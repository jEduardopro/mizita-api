<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent\Mappers;

use App\Domains\Accounts\Entities\Account;
use App\Models\User;
use DateTimeImmutable;

final class AccountMapper
{
    public function toEntity(User $model): Account
    {
        return Account::restore(
            id: $model->uuid,
            name: $model->name,
            email: $model->email,
            emailVerifiedAt: $model->email_verified_at === null
                ? null
                : DateTimeImmutable::createFromInterface($model->email_verified_at),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Account $account): array
    {
        return [
            'uuid' => $account->id,
            'name' => $account->name(),
            'email' => $account->email(),
            'email_verified_at' => $account->emailVerifiedAt(),
        ];
    }
}
