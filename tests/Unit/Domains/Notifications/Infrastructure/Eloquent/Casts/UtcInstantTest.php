<?php

declare(strict_types=1);

use App\Domains\Notifications\Infrastructure\Eloquent\Casts\UtcInstant;
use App\Domains\Notifications\Infrastructure\Eloquent\Models\StaffNotificationModel;

beforeEach(function () {
    $this->cast = new UtcInstant;
    $this->model = new StaffNotificationModel;
});

describe('reading a column', function () {
    it('reads null as no instant', function () {
        expect($this->cast->get($this->model, 'read_at', null, []))->toBeNull();
    });

    it('reads a stored timestamp as the same instant in utc', function (string $stored, string $utc) {
        $instant = $this->cast->get($this->model, 'read_at', $stored, []);

        expect($instant)->toBeInstanceOf(DateTimeImmutable::class)
            ->and($instant?->getTimezone()->getName())->toBe('UTC')
            ->and($instant?->format(DATE_ATOM))->toBe($utc);
    })->with([
        'already utc' => ['2026-03-10 08:00:00+00', '2026-03-10T08:00:00+00:00'],
        'madrid in winter' => ['2026-01-15 10:00:00+01', '2026-01-15T09:00:00+00:00'],
        'madrid in summer' => ['2026-07-15 10:00:00+02', '2026-07-15T08:00:00+00:00'],
    ]);
});

describe('writing a column', function () {
    it('writes null as null', function () {
        expect($this->cast->set($this->model, 'read_at', null, []))->toBeNull();
    });

    it('writes an instant from any zone as the same instant in utc', function (DateTimeImmutable $instant, string $stored) {
        expect($this->cast->set($this->model, 'read_at', $instant, []))->toBe($stored);
    })->with([
        'the last minute before spring forward in madrid' => [
            new DateTimeImmutable('2026-03-29 01:59:00', new DateTimeZone('Europe/Madrid')),
            '2026-03-29T00:59:00+00:00',
        ],
        'the first minute after spring forward in madrid' => [
            new DateTimeImmutable('2026-03-29 03:00:00', new DateTimeZone('Europe/Madrid')),
            '2026-03-29T01:00:00+00:00',
        ],
        'the first 02:30 on fall back day in madrid' => [
            new DateTimeImmutable('2026-10-25T02:30:00+02:00'),
            '2026-10-25T00:30:00+00:00',
        ],
        'the second 02:30 on fall back day in madrid' => [
            new DateTimeImmutable('2026-10-25T02:30:00+01:00'),
            '2026-10-25T01:30:00+00:00',
        ],
    ]);

    it('keeps the two instants of a repeated local hour apart', function () {
        $first = $this->cast->set($this->model, 'read_at', new DateTimeImmutable('2026-10-25T02:30:00+02:00'), []);
        $second = $this->cast->set($this->model, 'read_at', new DateTimeImmutable('2026-10-25T02:30:00+01:00'), []);

        expect($first)->not->toBe($second);
    });
});
