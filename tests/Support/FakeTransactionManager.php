<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\TransactionManager;
use Throwable;

final class FakeTransactionManager implements TransactionManager
{
    private int $runs = 0;

    private bool $running = false;

    private ?Throwable $commitFailure = null;

    public function failAtCommit(Throwable $failure): void
    {
        $this->commitFailure = $failure;
    }

    public function run(callable $work): mixed
    {
        $this->runs++;
        $this->running = true;

        try {
            $outcome = $work();

            if ($this->commitFailure !== null) {
                throw $this->commitFailure;
            }

            return $outcome;
        } finally {
            $this->running = false;
        }
    }

    public function runs(): int
    {
        return $this->runs;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }
}
