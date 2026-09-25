<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent;

use App\Domains\Accounts\Contracts\PasskeyDirectory;
use App\Domains\Accounts\Infrastructure\Eloquent\Mappers\PasskeyMapper;
use App\Domains\Accounts\Infrastructure\Eloquent\Models\PasskeyModel;
use App\Models\User;

final class EloquentPasskeyDirectory implements PasskeyDirectory
{
    public function __construct(
        private readonly PasskeyMapper $mapper,
    ) {}

    public function forAccount(string $accountId): array
    {
        return PasskeyModel::query()
            ->whereIn('user_id', User::withTrashed()->select('id')->where('uuid', $accountId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (PasskeyModel $passkey) => $this->mapper->toRegisteredPasskey($passkey))
            ->values()
            ->all();
    }
}
