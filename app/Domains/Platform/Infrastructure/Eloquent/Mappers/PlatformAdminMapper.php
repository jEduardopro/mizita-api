<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Eloquent\Mappers;

use App\Domains\Platform\Entities\PlatformAdmin;

final class PlatformAdminMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(PlatformAdmin $admin): array
    {
        return [
            'uuid' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email->value,
            'password' => $admin->passwordHash,
            'two_factor_secret' => $admin->twoFactorSecret,
            'two_factor_confirmed_at' => $admin->twoFactorConfirmedAt,
            'created_at' => $admin->createdAt,
        ];
    }
}
