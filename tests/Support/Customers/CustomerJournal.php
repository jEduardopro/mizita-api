<?php

declare(strict_types=1);

namespace Tests\Support\Customers;

use Tests\Support\FakeTransactionManager;

final class CustomerJournal
{
    /**
     * @var list<string>
     */
    public array $entries = [];

    /**
     * @var list<string>
     */
    public array $outsideTransaction = [];

    public function __construct(
        private readonly ?FakeTransactionManager $transactions = null,
    ) {}

    public function record(string $entry): void
    {
        $this->entries[] = $entry;

        if ($this->transactions !== null && ! $this->transactions->isRunning()) {
            $this->outsideTransaction[] = $entry;
        }
    }
}
