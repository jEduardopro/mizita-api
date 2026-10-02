<?php

declare(strict_types=1);

use App\Domains\Platform\Application\UseCases\StopImpersonation;
use Tests\Support\Platform\RecordingImpersonationSession;

beforeEach(function () {
    $this->session = new RecordingImpersonationSession;
    $this->stop = new StopImpersonation($this->session);
});

it('ends the impersonation session exactly once', function () {
    $this->stop->handle();

    expect($this->session->endings)->toBe(1);
});

it('succeeds with nothing to hand back', function () {
    $response = $this->stop->handle();

    expect($response->succeeded())->toBeTrue()
        ->and($response->value())->toBeNull();
});

it('starts nothing while stopping', function () {
    $this->stop->handle();

    expect($this->session->started)->toBe([]);
});
