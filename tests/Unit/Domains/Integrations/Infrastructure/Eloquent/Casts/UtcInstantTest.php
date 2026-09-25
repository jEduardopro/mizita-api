<?php

declare(strict_types=1);

use App\Domains\Integrations\Infrastructure\Eloquent\Casts\UtcInstant;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarConnectionModel;

beforeEach(function () {
    $this->cast = new UtcInstant;
    $this->model = new CalendarConnectionModel;

    $this->read = fn (mixed $value): ?DateTimeImmutable => $this->cast->get($this->model, 'connected_at', $value, []);
    $this->write = fn (mixed $value): ?string => $this->cast->set($this->model, 'connected_at', $value, []);
});

describe('reading a column', function () {
    it('reads a timestamptz back as the same instant in UTC', function () {
        expect(($this->read)('2026-03-29 12:00:00+02')->format(DATE_ATOM))->toBe('2026-03-29T10:00:00+00:00');
    });

    it('tells apart the two 01:30 of a fall-back day in Madrid', function () {
        $summerTime = ($this->read)('2026-10-25 02:30:00+02');
        $winterTime = ($this->read)('2026-10-25 02:30:00+01');

        expect($summerTime->format(DATE_ATOM))->toBe('2026-10-25T00:30:00+00:00')
            ->and($winterTime->format(DATE_ATOM))->toBe('2026-10-25T01:30:00+00:00');
    });

    it('reads a missing value as null', function () {
        expect(($this->read)(null))->toBeNull();
    });
});

describe('writing a column', function () {
    it('writes a local instant as the same instant in UTC', function () {
        $local = new DateTimeImmutable('2026-03-29 03:30:00', new DateTimeZone('Europe/Madrid'));

        expect(($this->write)($local))->toBe('2026-03-29T01:30:00+00:00');
    });

    it('writes a mutable date time without changing the instant', function () {
        expect(($this->write)(new DateTime('2026-03-29T08:30:00+00:00')))->toBe('2026-03-29T08:30:00+00:00');
    });

    it('writes a string instant in UTC', function () {
        expect(($this->write)('2026-10-25T02:30:00+01:00'))->toBe('2026-10-25T01:30:00+00:00');
    });

    it('writes null as null', function () {
        expect(($this->write)(null))->toBeNull();
    });
});
