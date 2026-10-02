<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use App\Domains\Platform\Contracts\ImpersonationAuditTrail;
use App\Domains\Platform\ValueObjects\Impersonation;
use DateTimeImmutable;
use Tests\Support\FakeTransactionManager;
use Throwable;

final class RecordingImpersonationAuditTrail implements ImpersonationAuditTrail
{
    /**
     * @var list<array{impersonation: Impersonation, ipAddress: ?string, insideTransaction: bool}>
     */
    public array $started = [];

    /**
     * @var list<array{impersonationId: string, endedAt: DateTimeImmutable}>
     */
    public array $ended = [];

    private ?Throwable $startFailure = null;

    public function __construct(
        private readonly ?FakeTransactionManager $transactions = null,
    ) {}

    public function failingToRecordStartWith(Throwable $failure): self
    {
        $this->startFailure = $failure;

        return $this;
    }

    public function recordStarted(Impersonation $impersonation, ?string $ipAddress): void
    {
        if ($this->startFailure !== null) {
            throw $this->startFailure;
        }

        $this->started[] = [
            'impersonation' => $impersonation,
            'ipAddress' => $ipAddress,
            'insideTransaction' => $this->transactions?->isRunning() ?? false,
        ];
    }

    public function recordEnded(string $impersonationId, DateTimeImmutable $endedAt): void
    {
        $this->ended[] = ['impersonationId' => $impersonationId, 'endedAt' => $endedAt];
    }
}
