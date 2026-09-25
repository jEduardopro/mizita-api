<?php

declare(strict_types=1);

use App\Domains\Integrations\Infrastructure\Queue\SyncAppointmentToCalendar;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

beforeEach(function () {
    $this->job = new SyncAppointmentToCalendar(IntegrationsFixtures::APPOINTMENT_ID);
});

it('waits for the transaction that changed the appointment to commit', function () {
    expect($this->job)->toBeInstanceOf(ShouldQueueAfterCommit::class);
});

it('never syncs the same appointment twice at once', function () {
    $middleware = $this->job->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->key)->toBe(IntegrationsFixtures::APPOINTMENT_ID);
});

it('lets two different appointments sync side by side', function () {
    $other = new SyncAppointmentToCalendar('01930000-0000-7000-8000-0000000000e2');

    expect($other->middleware()[0]->key)->not->toBe($this->job->middleware()[0]->key);
});

it('releases a locked sync to retry instead of failing it', function () {
    expect($this->job->middleware()[0]->releaseAfter)->toBeGreaterThan(0);
});
