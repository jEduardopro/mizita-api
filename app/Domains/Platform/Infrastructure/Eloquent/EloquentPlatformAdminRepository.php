<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Eloquent;

use App\Domains\Platform\Contracts\PlatformAdminRepository;
use App\Domains\Platform\Entities\PlatformAdmin;
use App\Domains\Platform\Exceptions\PlatformAdminAlreadyExists;
use App\Domains\Platform\Infrastructure\Eloquent\Mappers\PlatformAdminMapper;
use App\Domains\Platform\Infrastructure\Eloquent\Models\PlatformAdminModel;
use App\Domains\Platform\ValueObjects\PlatformAdminEmail;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentPlatformAdminRepository implements PlatformAdminRepository
{
    public function __construct(
        private readonly PlatformAdminMapper $mapper,
    ) {}

    public function existsByEmail(PlatformAdminEmail $email): bool
    {
        return PlatformAdminModel::withTrashed()->where('email', $email->value)->exists();
    }

    public function save(PlatformAdmin $admin): void
    {
        try {
            PlatformAdminModel::query()->updateOrCreate(
                ['uuid' => $admin->id],
                $this->mapper->toAttributes($admin),
            );
        } catch (UniqueConstraintViolationException) {
            throw PlatformAdminAlreadyExists::withEmail($admin->email->value);
        }
    }
}
