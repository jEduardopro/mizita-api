<?php

declare(strict_types=1);

namespace App\Domains\Platform\Application\Dtos;

use App\Domains\Platform\Entities\PlatformAdmin;

final readonly class PlatformAdminData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {}

    public static function fromEntity(PlatformAdmin $admin): self
    {
        return new self(
            id: $admin->id,
            name: $admin->name,
            email: $admin->email->value,
        );
    }
}
