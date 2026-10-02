<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\SignedInPlatformAdmin;

final class FakeSignedInPlatformAdmin implements SignedInPlatformAdmin
{
    public int $descriptions = 0;

    /**
     * @param  array{name: string, email: string}|null  $admin
     */
    public function __construct(
        private readonly ?array $admin = null,
    ) {}

    public function describe(): ?array
    {
        $this->descriptions++;

        return $this->admin;
    }
}
