<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Notifications\Application\Doubles;

final class NotificationsJournal
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
