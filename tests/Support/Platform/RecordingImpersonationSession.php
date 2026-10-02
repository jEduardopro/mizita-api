<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use App\Domains\Platform\Contracts\ImpersonationSession;
use App\Domains\Platform\ValueObjects\Impersonation;
use Tests\Support\FakeTransactionManager;
use Throwable;

final class RecordingImpersonationSession implements ImpersonationSession
{
    /**
     * @var list<Impersonation>
     */
    public array $started = [];

    /**
     * @var list<bool>
     */
    public array $startedInsideTransaction = [];

    public int $endings = 0;

    private ?Impersonation $current = null;

    private ?Throwable $startRefusal = null;

    public function __construct(
        private readonly ?FakeTransactionManager $transactions = null,
    ) {}

    public function holding(Impersonation $impersonation): self
    {
        $this->current = $impersonation;

        return $this;
    }

    public function refusingToStartWith(Throwable $refusal): self
    {
        $this->startRefusal = $refusal;

        return $this;
    }

    public function start(Impersonation $impersonation): void
    {
        if ($this->startRefusal !== null) {
            throw $this->startRefusal;
        }

        $this->started[] = $impersonation;
        $this->startedInsideTransaction[] = $this->transactions?->isRunning() ?? false;
        $this->current = $impersonation;
    }

    public function current(): ?Impersonation
    {
        return $this->current;
    }

    public function end(): void
    {
        $this->endings++;
        $this->current = null;
    }
}
