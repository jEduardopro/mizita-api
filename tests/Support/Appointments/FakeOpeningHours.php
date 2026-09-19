<?php

declare(strict_types=1);

namespace Tests\Support\Appointments;

use App\Domains\Appointments\Contracts\OpeningHours;

final class FakeOpeningHours implements OpeningHours
{
    /**
     * @var list<string>
     */
    public array $asked = [];

    private bool $open = true;

    public function close(): self
    {
        $this->open = false;

        return $this;
    }

    public function isOpenNow(string $businessId): bool
    {
        $this->asked[] = $businessId;

        return $this->open;
    }
}
