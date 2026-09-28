<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Eloquent\Casts\UtcInstant;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\SubscriptionModel;

beforeEach(function () {
    $this->cast = new UtcInstant;
    $this->model = new SubscriptionModel;
});

describe('reading', function () {
    it('reads a stored instant as an immutable UTC instant', function (string $stored) {
        $instant = $this->cast->get($this->model, 'starts_at', $stored, []);

        expect($instant)->toBeInstanceOf(DateTimeImmutable::class)
            ->and($instant?->format(DATE_ATOM))->toBe('2026-03-29T01:30:00+00:00')
            ->and($instant?->getTimezone()->getName())->toBe('UTC');
    })->with([
        'postgres utc' => '2026-03-29 01:30:00+00',
        'postgres with an offset' => '2026-03-29 03:30:00+02',
        'atom' => '2026-03-29T01:30:00+00:00',
    ]);

    it('reads a date time object as the same instant in UTC', function () {
        $instant = $this->cast->get($this->model, 'starts_at', new DateTime('2026-10-25 00:30:00', new DateTimeZone('Europe/Madrid')), []);

        expect($instant?->getTimezone()->getName())->toBe('UTC')
            ->and($instant?->format(DATE_ATOM))->toBe('2026-10-24T22:30:00+00:00');
    });

    it('reads null as null', function () {
        expect($this->cast->get($this->model, 'ends_at', null, []))->toBeNull();
    });
});

describe('writing', function () {
    it('writes an instant as ATOM in UTC', function () {
        $stored = $this->cast->set($this->model, 'ends_at', new DateTimeImmutable('2026-03-29T00:00:00+01:00'), []);

        expect($stored)->toBe('2026-03-28T23:00:00+00:00');
    });

    it('writes a string instant as ATOM in UTC', function () {
        expect($this->cast->set($this->model, 'ends_at', '2026-10-26T00:00:00+01:00', []))->toBe('2026-10-25T23:00:00+00:00');
    });

    it('writes null as null', function () {
        expect($this->cast->set($this->model, 'ends_at', null, []))->toBeNull();
    });
});
