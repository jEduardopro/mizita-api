<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use App\Domains\Platform\Contracts\AuthenticatorApp;

final class FakeAuthenticatorApp implements AuthenticatorApp
{
    /**
     * @var list<array{string, string}>
     */
    public array $checked = [];

    /**
     * @param  array<string, string>  $codeBySecret
     */
    public function __construct(
        private readonly array $codeBySecret = [],
    ) {}

    public function confirms(string $secret, string $code): bool
    {
        $this->checked[] = [$secret, $code];

        return ($this->codeBySecret[$secret] ?? null) === $code;
    }
}
