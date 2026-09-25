<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AccountsIncludingDeletedUserProvider extends EloquentUserProvider
{
    public const DRIVER = 'accounts_including_deleted';

    /**
     * @param  Model|null  $model
     * @return Builder<User>
     */
    protected function newModelQuery($model = null): Builder
    {
        /** @var Builder<User> $query */
        $query = parent::newModelQuery($model);

        return $query->withTrashed();
    }
}
