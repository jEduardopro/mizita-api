<?php

declare(strict_types=1);

use App\Domains\Industries\Entities\Industry;
use App\Domains\Industries\Exceptions\IndustryAlreadyActive;
use App\Domains\Industries\Exceptions\IndustryAlreadyInactive;
use App\Domains\Industries\Exceptions\InvalidIndustryKey;

const INDUSTRY_ID = '01930000-0000-7000-8000-0000000000a1';

function industryCreatedAt(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-01-01T12:00:00+00:00');
}

describe('create', function () {
    it('opens a catalog row active, at the position it was given', function () {
        $industry = Industry::create(
            id: INDUSTRY_ID,
            key: 'barbershop',
            position: 3,
            now: industryCreatedAt(),
        );

        expect($industry->id)->toBe(INDUSTRY_ID)
            ->and($industry->key())->toBe('barbershop')
            ->and($industry->position())->toBe(3)
            ->and($industry->isActive())->toBeTrue()
            ->and($industry->createdAt)->toEqual(industryCreatedAt());
    });

    it('trims the key, because the padding is never part of the identity', function () {
        expect(Industry::create(INDUSTRY_ID, "  barbershop\t", 1, industryCreatedAt())->key())
            ->toBe('barbershop');
    });

    it('rejects a blank key', function (string $key) {
        expect(fn () => Industry::create(INDUSTRY_ID, $key, 1, industryCreatedAt()))
            ->toThrow(InvalidIndustryKey::class, 'An industry key cannot be empty.');
    })->with([
        'empty' => [''],
        'spaces' => ['   '],
        'tab' => ["\t"],
        'newline' => ["\n"],
    ]);

    it('accepts a position of zero and a negative one', function (int $position) {
        expect(Industry::create(INDUSTRY_ID, 'barbershop', $position, industryCreatedAt())->position())
            ->toBe($position);
    })->with([
        'zero' => [0],
        'negative' => [-1],
    ]);

    it('keeps a key with accents and non-latin characters intact', function (string $key) {
        expect(Industry::create(INDUSTRY_ID, $key, 1, industryCreatedAt())->key())->toBe($key);
    })->with([
        'accented' => ['peluquería'],
        'non-latin' => ['理容室'],
    ]);
});

describe('restore', function () {
    it('rehydrates a row exactly as it was stored', function () {
        $industry = Industry::restore(
            id: INDUSTRY_ID,
            key: 'barbershop',
            position: 7,
            active: false,
            createdAt: industryCreatedAt(),
        );

        expect($industry->id)->toBe(INDUSTRY_ID)
            ->and($industry->key())->toBe('barbershop')
            ->and($industry->position())->toBe(7)
            ->and($industry->isActive())->toBeFalse()
            ->and($industry->createdAt)->toEqual(industryCreatedAt());
    });

    it('skips the creation-time rules by design', function () {
        $industry = Industry::restore(INDUSTRY_ID, '  ', 1, true, industryCreatedAt());

        expect($industry->key())->toBe('  ');
    });
});

describe('activate and deactivate', function () {
    it('retires an industry from the catalog without deleting the record', function () {
        $industry = Industry::create(INDUSTRY_ID, 'barbershop', 1, industryCreatedAt());

        $industry->deactivate();

        expect($industry->isActive())->toBeFalse();
    });

    it('puts a retired industry back in the catalog', function () {
        $industry = Industry::restore(INDUSTRY_ID, 'barbershop', 1, false, industryCreatedAt());

        $industry->activate();

        expect($industry->isActive())->toBeTrue();
    });

    it('refuses to deactivate an industry that is already retired', function () {
        $industry = Industry::restore(INDUSTRY_ID, 'barbershop', 1, false, industryCreatedAt());

        expect(fn () => $industry->deactivate())
            ->toThrow(IndustryAlreadyInactive::class, 'Industry ['.INDUSTRY_ID.'] is already inactive.')
            ->and($industry->isActive())->toBeFalse();
    });

    it('refuses to activate an industry that is already offered', function () {
        $industry = Industry::create(INDUSTRY_ID, 'barbershop', 1, industryCreatedAt());

        expect(fn () => $industry->activate())
            ->toThrow(IndustryAlreadyActive::class, 'Industry ['.INDUSTRY_ID.'] is already active.')
            ->and($industry->isActive())->toBeTrue();
    });

    it('lets the row travel back and forth between the two states', function () {
        $industry = Industry::create(INDUSTRY_ID, 'barbershop', 1, industryCreatedAt());

        $industry->deactivate();
        $industry->activate();

        expect($industry->isActive())->toBeTrue();
    });
});
