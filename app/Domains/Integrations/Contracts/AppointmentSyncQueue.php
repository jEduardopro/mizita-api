<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

interface AppointmentSyncQueue
{
    public function schedule(string $appointmentId): void;
}
