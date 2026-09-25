<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Auth;

use App\Domains\Accounts\Contracts\TemporaryPasswordVault;
use App\Models\User;
use Illuminate\Auth\Events\Login;

final class DiscardTemporaryPasswordOnLogin
{
    public function __construct(
        private readonly TemporaryPasswordVault $vault,
    ) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->vault->discard((string) $event->user->uuid);
    }
}
