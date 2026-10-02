<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use LogicException;

final class FakeAuthFactory implements Factory
{
    /**
     * @param  array<string, Guard|StatefulGuard>  $guards
     */
    public function __construct(
        private readonly array $guards,
    ) {}

    public function guard($name = null): Guard|StatefulGuard
    {
        return $this->guards[$name] ?? throw new LogicException("FakeAuthFactory holds no [{$name}] guard.");
    }

    public function shouldUse($name): void {}
}
