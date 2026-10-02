<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Auth;

use App\Domains\Platform\Infrastructure\Eloquent\Models\PlatformAdminModel;
use Illuminate\Contracts\Hashing\Hasher;

final class PlatformCredentials
{
    public function __construct(
        private readonly Hasher $hasher,
    ) {}

    public function adminIdMatching(string $email, string $password): ?string
    {
        $admin = $this->enrolledAdminWithEmail($email);

        if ($admin === null) {
            $this->spendTheTimeAPasswordCheckTakes($password);

            return null;
        }

        if (! $this->hasher->check($password, (string) $admin->getAuthPassword())) {
            return null;
        }

        return (string) $admin->uuid;
    }

    private function enrolledAdminWithEmail(string $email): ?PlatformAdminModel
    {
        return PlatformAdminModel::query()
            ->where('email', mb_strtolower(trim($email)))
            ->whereNotNull('two_factor_confirmed_at')
            ->first();
    }

    private function spendTheTimeAPasswordCheckTakes(string $password): void
    {
        $this->hasher->make($password);
    }
}
