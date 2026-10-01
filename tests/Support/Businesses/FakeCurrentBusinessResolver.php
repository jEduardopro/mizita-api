<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Shared\Contracts\CurrentBusinessResolver;
use LogicException;
use Throwable;

final class FakeCurrentBusinessResolver implements CurrentBusinessResolver
{
    /**
     * @var list<array{accountId: string, requestedBusinessId: ?string}>
     */
    public array $resolutions = [];

    private ?Throwable $refusal = null;

    public function __construct(
        private readonly ?string $verdict = null,
    ) {}

    public function refusingWith(Throwable $refusal): self
    {
        $this->refusal = $refusal;

        return $this;
    }

    public function resolveFor(string $accountId, ?string $requestedBusinessId): string
    {
        $this->resolutions[] = ['accountId' => $accountId, 'requestedBusinessId' => $requestedBusinessId];

        if ($this->refusal !== null) {
            throw $this->refusal;
        }

        return $this->verdict
            ?? $requestedBusinessId
            ?? throw new LogicException('FakeCurrentBusinessResolver has no business to resolve to.');
    }
}
