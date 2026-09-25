<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\AppointmentSyncQueue;

final class FakeAppointmentSyncQueue implements AppointmentSyncQueue
{
    /**
     * @var list<string>
     */
    public array $scheduled = [];

    public function schedule(string $appointmentId): void
    {
        $this->scheduled[] = $appointmentId;
    }
}
