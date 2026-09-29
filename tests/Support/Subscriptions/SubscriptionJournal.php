<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

final class SubscriptionJournal
{
    /**
     * @var list<string>
     */
    public array $entries = [];

    public function record(string $entry): void
    {
        $this->entries[] = $entry;
    }
}
