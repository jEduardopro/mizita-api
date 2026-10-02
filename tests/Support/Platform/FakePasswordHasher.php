<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use App\Domains\Platform\Contracts\PasswordHasher;

final class FakePasswordHasher implements PasswordHasher
{
    public const PREFIX = 'hashed:';

    /**
     * @var list<string>
     */
    public array $hashed = [];

    public function hash(string $password): string
    {
        $this->hashed[] = $password;

        return self::PREFIX.$password;
    }
}
