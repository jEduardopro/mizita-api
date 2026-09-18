<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\InvalidAppointmentNotes;
use App\Domains\Appointments\ValueObjects\AppointmentNotes;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

describe('notes taken down for an appointment', function () {
    it('keeps what was written', function () {
        expect(AppointmentNotes::fromString('Allergic to ammonia.')->value)->toBe('Allergic to ammonia.');
    });

    it('trims the padding around what was written', function () {
        expect(AppointmentNotes::fromString("  Allergic to ammonia. \t ")->value)->toBe('Allergic to ammonia.');
    });

    it('keeps the line breaks a note was written with', function () {
        expect(AppointmentNotes::fromString("First visit.\nAllergic to ammonia.")->value)
            ->toBe("First visit.\nAllergic to ammonia.");
    });

    it('keeps the accents and the punctuation a person writes with', function () {
        expect(AppointmentNotes::fromString('Prefiere la tarde; alérgica al amoníaco.')->value)
            ->toBe('Prefiere la tarde; alérgica al amoníaco.');
    });

    it('keeps notes as long as the column holds', function () {
        expect(AppointmentNotes::fromString(str_repeat('a', AppointmentNotes::MAXIMUM_LENGTH))->value)
            ->toHaveLength(AppointmentNotes::MAXIMUM_LENGTH);
    });

    it('refuses notes one character past what the column holds', function () {
        expect(fn () => AppointmentNotes::fromString(str_repeat('a', AppointmentNotes::MAXIMUM_LENGTH + 1)))
            ->toThrow(InvalidAppointmentNotes::class, 'may not run past 2000 characters');
    });

    it('measures the notes after trimming them, so padding never costs a receptionist their note', function () {
        expect(AppointmentNotes::fromString('  '.str_repeat('a', AppointmentNotes::MAXIMUM_LENGTH).'  ')->value)
            ->toHaveLength(AppointmentNotes::MAXIMUM_LENGTH);
    });

    it('counts characters rather than bytes, so an accent is not worth two letters', function () {
        $notes = str_repeat('á', AppointmentNotes::MAXIMUM_LENGTH);

        expect(AppointmentNotes::fromString($notes)->value)->toBe($notes);
    });

    it('refuses accented notes one character too long, counting characters', function () {
        expect(fn () => AppointmentNotes::fromString(str_repeat('á', AppointmentNotes::MAXIMUM_LENGTH + 1)))
            ->toThrow(InvalidAppointmentNotes::class);
    });

    it('takes notes that say nothing as empty notes rather than refusing them', function (string $raw) {
        expect(AppointmentNotes::fromString($raw)->value)->toBe('');
    })->with(['empty' => '', 'spaces' => '   ', 'tab' => "\t", 'newline' => "\n"]);
});

describe('notes that may not have been taken at all', function () {
    it('reads nothing written as no notes at all', function (?string $raw) {
        expect(AppointmentNotes::fromNullable($raw))->toBeNull();
    })->with([
        'null' => null,
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
        'every kind of blank at once' => " \t\n ",
    ]);

    it('hands back notes when something was written', function () {
        expect(AppointmentNotes::fromNullable('  Allergic to ammonia.  ')?->value)->toBe('Allergic to ammonia.');
    });

    it('holds notes that may be absent to the same length it holds any others to', function () {
        expect(fn () => AppointmentNotes::fromNullable(str_repeat('a', AppointmentNotes::MAXIMUM_LENGTH + 1)))
            ->toThrow(InvalidAppointmentNotes::class);
    });
});

describe('restoring notes from persistence', function () {
    it('takes the row as it stands rather than judging it again', function () {
        expect(AppointmentNotes::restore(str_repeat('a', AppointmentNotes::MAXIMUM_LENGTH + 1))->value)
            ->toHaveLength(AppointmentNotes::MAXIMUM_LENGTH + 1);
    });

    it('leaves the notes exactly as the row spells them, padding included', function () {
        expect(AppointmentNotes::restore('  Allergic to ammonia.  ')->value)->toBe('  Allergic to ammonia.  ');
    });
});

it('refuses with a failure the responder can classify', function () {
    $refusal = null;

    try {
        AppointmentNotes::fromString(str_repeat('a', AppointmentNotes::MAXIMUM_LENGTH + 1));
    } catch (InvalidAppointmentNotes $caught) {
        $refusal = $caught;
    }

    expect($refusal)->toBeInstanceOf(DomainFailure::class)
        ->and($refusal?->errorCode())->toBe('invalid_appointment_notes')
        ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
});
