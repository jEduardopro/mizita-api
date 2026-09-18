<?php

declare(strict_types=1);

namespace Tests\Support\Appointments;

final class AppointmentJournal
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
