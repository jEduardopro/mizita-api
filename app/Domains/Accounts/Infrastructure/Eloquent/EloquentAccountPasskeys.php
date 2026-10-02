<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent;

use App\Domains\Accounts\Contracts\AccountPasskeys;
use App\Domains\Accounts\Infrastructure\Eloquent\Models\PasskeyModel;
use App\Models\User;

final class EloquentAccountPasskeys implements AccountPasskeys
{
    public function deleteAllOf(string $accountId): void
    {
        PasskeyModel::query()
            ->whereIn('user_id', User::withTrashed()->select('id')->where('uuid', $accountId))
            ->delete();
    }
}
