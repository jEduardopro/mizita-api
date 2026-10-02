<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use App\Domains\Platform\Contracts\ImpersonationSession;
use App\Domains\Platform\ValueObjects\Impersonation;
use Throwable;

final class RecordingImpersonationSession implements ImpersonationSession
{
    /**
     * @var list<Impersonation>
     */
    public array $started = [];

    public int $endings = 0;

    private ?Throwable $startRefusal = null;

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
    }

    public function end(): void
    {
        $this->endings++;
    }
}
