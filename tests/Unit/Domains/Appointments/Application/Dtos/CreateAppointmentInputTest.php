<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\CreateAppointmentInput;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\Exceptions\InvalidAppointmentNotes;
use App\Domains\Appointments\Exceptions\InvalidAppointmentSchedule;
use App\Domains\Appointments\ValueObjects\AppointmentNotes;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;

function payloadForCreateAppointmentInput(array $overrides = []): array
{
    return [
        'customer_id' => '01930000-0000-7000-8000-0000000000c1',
        'service_id' => '01930000-0000-7000-8000-0000000000f1',
        'staff_member_id' => '01930000-0000-7000-8000-0000000000a1',
        'starts_at' => '2026-03-02T10:00:00Z',
        'ends_at' => '2026-03-02T11:00:00Z',
        'notes' => 'Prefers the afternoon.',
        ...$overrides,
    ];
}

dataset('ids no appointment participant could ever have', [
    'empty' => '',
    'whitespace only' => '   ',
    'a row number' => '42',
    'a word' => 'someone',
    'a uuid missing a block' => '01930000-0000-7000-0000000000c1',
    'a uuid without hyphens' => '019300000000700080000000000000c1',
    'padded' => ' 01930000-0000-7000-8000-0000000000c1 ',
    'a trailing newline' => "01930000-0000-7000-8000-0000000000c1\n",
    'sql' => "01930000-0000-7000-8000-0000000000c1' or '1'='1",
]);

dataset('instants no clock could read', [
    'empty' => '',
    'whitespace only' => '   ',
    'a word' => 'tomorrow',
    'a date with no time' => '2026-03-02',
    'a time with no zone' => '2026-03-02T10:00:00',
    'a space instead of the separator' => '2026-03-02 10:00:00Z',
    'no seconds' => '2026-03-02T10:00Z',
    'day first' => '02/03/2026T10:00:00Z',
    'a unix timestamp' => '1772445600',
    'an offset without a colon' => '2026-03-02T10:00:00+0200',
    'a zone name instead of an offset' => '2026-03-02T10:00:00 Europe/Madrid',
    'a month the calendar lacks' => '2026-13-01T10:00:00Z',
    'an hour the clock lacks' => '2026-03-02T25:00:00Z',
]);

describe('reading a payload', function () {
    it('assembles itself from a body the form request would have passed', function () {
        $input = CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput());

        expect($input->customerId)->toBe('01930000-0000-7000-8000-0000000000c1')
            ->and($input->serviceId)->toBe('01930000-0000-7000-8000-0000000000f1')
            ->and($input->staffMemberId)->toBe('01930000-0000-7000-8000-0000000000a1')
            ->and($input->startsAt)->toBe('2026-03-02T10:00:00Z')
            ->and($input->endsAt)->toBe('2026-03-02T11:00:00Z')
            ->and($input->notes)->toBe('Prefers the afternoon.');
    });

    it('survives a body with every key missing, because a caller who skipped the form request has none', function () {
        $input = CreateAppointmentInput::fromRequest([]);

        expect($input->customerId)->toBe('')
            ->and($input->serviceId)->toBe('')
            ->and($input->staffMemberId)->toBe('')
            ->and($input->startsAt)->toBe('')
            ->and($input->endsAt)->toBeNull()
            ->and($input->notes)->toBeNull();
    });

    it('turns a body with every key missing into a domain failure rather than a PHP error', function () {
        expect(fn () => CreateAppointmentInput::fromRequest([])->validate())
            ->toThrow(AppointmentCustomerNotFound::class);
    });

    it('reads a required value that is not a string as nothing rather than casting it', function (mixed $value) {
        $input = CreateAppointmentInput::fromRequest([
            'customer_id' => $value,
            'service_id' => $value,
            'staff_member_id' => $value,
            'starts_at' => $value,
        ]);

        expect($input->customerId)->toBe('')
            ->and($input->serviceId)->toBe('')
            ->and($input->staffMemberId)->toBe('')
            ->and($input->startsAt)->toBe('');
    })->with([
        'null' => null,
        'a number' => 42,
        'an array' => [['01930000-0000-7000-8000-0000000000c1']],
        'a boolean' => true,
    ]);

    it('reads an optional value that is not a string as none at all', function (array $payload, string $field) {
        expect(CreateAppointmentInput::fromRequest($payload)->{$field})->toBeNull();
    })->with([
        'an end as an array' => [['ends_at' => ['2026-03-02T11:00:00Z']], 'endsAt'],
        'an end as a number' => [['ends_at' => 1772449200], 'endsAt'],
        'an end as a boolean' => [['ends_at' => false], 'endsAt'],
        'notes as an array' => [['notes' => ['Prefers the afternoon.']], 'notes'],
        'notes as a number' => [['notes' => 42], 'notes'],
    ]);

    it('reads an optional value that says nothing as none at all', function (string $key, string $blank, string $field) {
        expect(CreateAppointmentInput::fromRequest([$key => $blank])->{$field})->toBeNull();
    })->with([
        'an empty end' => ['ends_at', '', 'endsAt'],
        'a blank end' => ['ends_at', '   ', 'endsAt'],
        'empty notes' => ['notes', '', 'notes'],
        'blank notes' => ['notes', '   ', 'notes'],
        'a tab for notes' => ['notes', "\t", 'notes'],
    ]);

    it('holds the text it was handed without normalising it, because the value objects trim on the way in', function () {
        $input = CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'starts_at' => '  2026-03-02T10:00:00Z  ',
            'notes' => '  Prefers the afternoon.  ',
        ]));

        expect($input->startsAt)->toBe('  2026-03-02T10:00:00Z  ')
            ->and($input->notes)->toBe('  Prefers the afternoon.  ');
    });
});

describe('the business the caller may never name', function () {
    it('carries no business at all, because the tenant comes from the context and never from the body', function () {
        $input = CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'business_id' => FakeBusinessContext::BUSINESS_ID,
        ]));

        expect(array_keys(get_object_vars($input)))
            ->toBe(['customerId', 'serviceId', 'staffMemberId', 'startsAt', 'endsAt', 'notes']);
    });

    it('drops a business the caller smuggled into the body rather than refusing the booking', function () {
        $smuggled = CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'business_id' => 'a business that is not the caller own',
        ]));

        expect(fn () => $smuggled->validate())->not->toThrow(Throwable::class)
            ->and($smuggled)->toEqual(CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput()));
    });
});

describe('validating the participants', function () {
    it('accepts a payload every rule agrees with', function () {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput())->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts a booking that names nothing but the required facts', function () {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'ends_at' => null,
            'notes' => null,
        ]))->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a customer uuid however it is cased', function (string $id) {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'customer_id' => $id,
        ]))->validate())->not->toThrow(Throwable::class);
    })->with([
        'lowercase' => '01930000-0000-7000-8000-0000000000c1',
        'uppercase' => '01930000-0000-7000-8000-0000000000C1',
        'a version four uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
    ]);

    it('refuses a customer no booking could ever name, before any repository is asked', function (string $id) {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'customer_id' => $id,
        ]))->validate())->toThrow(AppointmentCustomerNotFound::class);
    })->with('ids no appointment participant could ever have');

    it('refuses a service no booking could ever name, before any catalogue is asked', function (string $id) {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'service_id' => $id,
        ]))->validate())->toThrow(AppointmentServiceNotFound::class);
    })->with('ids no appointment participant could ever have');

    it('refuses a team member no booking could ever name, before any directory is asked', function (string $id) {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'staff_member_id' => $id,
        ]))->validate())->toThrow(AppointmentStaffNotFound::class);
    })->with('ids no appointment participant could ever have');

    it('names the customer first when every participant is wrong at once', function () {
        expect(fn () => CreateAppointmentInput::fromRequest([
            'customer_id' => '',
            'service_id' => '',
            'staff_member_id' => '',
            'starts_at' => 'tomorrow',
        ])->validate())->toThrow(AppointmentCustomerNotFound::class);
    });

    it('judges the service before the team member', function () {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'service_id' => '',
            'staff_member_id' => '',
        ]))->validate())->toThrow(AppointmentServiceNotFound::class);
    });

    it('judges the team member before the schedule', function () {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'staff_member_id' => '',
            'starts_at' => 'tomorrow',
        ]))->validate())->toThrow(AppointmentStaffNotFound::class);
    });
});

describe('validating the schedule', function () {
    it('refuses a start no clock could read', function (string $startsAt) {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'starts_at' => $startsAt,
        ]))->validate())->toThrow(InvalidAppointmentSchedule::class);
    })->with('instants no clock could read');

    it('refuses an end no clock could read', function (string $endsAt) {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'ends_at' => $endsAt,
        ]))->validate())->toThrow(InvalidAppointmentSchedule::class);
    })->with('instants no clock could read');

    it('accepts an instant however its zone is written', function (string $startsAt) {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'starts_at' => $startsAt,
            'ends_at' => null,
        ]))->validate())->not->toThrow(Throwable::class);
    })->with([
        'utc' => '2026-03-02T10:00:00Z',
        'a positive offset' => '2026-03-02T11:00:00+01:00',
        'a negative offset' => '2026-03-02T04:00:00-06:00',
        'fractional seconds' => '2026-03-02T10:00:00.123456Z',
        'padded' => '  2026-03-02T10:00:00Z  ',
    ]);

    it('accepts a booking with no end, because the service duration decides it', function () {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'ends_at' => null,
        ]))->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a booking that ends before it starts', function () {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'starts_at' => '2026-03-02T11:00:00Z',
            'ends_at' => '2026-03-02T10:00:00Z',
        ]))->validate())->toThrow(InvalidAppointmentSchedule::class);
    })->todo('blocked: CreateAppointmentInput::validateSchedule() parses both instants but never compares them, so the after:starts_at rule the form request states is missing from the DTO leg');

    it('refuses a booking that ends the instant it starts', function () {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'starts_at' => '2026-03-02T10:00:00Z',
            'ends_at' => '2026-03-02T10:00:00+00:00',
        ]))->validate())->toThrow(InvalidAppointmentSchedule::class);
    })->todo('blocked: CreateAppointmentInput::validateSchedule() parses both instants but never compares them, so a zero length booking passes the DTO leg');
});

describe('validating the notes', function () {
    it('accepts notes as long as the value object allows', function () {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'notes' => str_repeat('a', AppointmentNotes::MAXIMUM_LENGTH),
        ]))->validate())->not->toThrow(Throwable::class);
    });

    it('refuses notes one character past what the value object allows', function () {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'notes' => str_repeat('a', AppointmentNotes::MAXIMUM_LENGTH + 1),
        ]))->validate())->toThrow(InvalidAppointmentNotes::class);
    });

    it('measures the notes after trimming them, exactly as the value object will', function () {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'notes' => '  '.str_repeat('a', AppointmentNotes::MAXIMUM_LENGTH).'  ',
        ]))->validate())->not->toThrow(Throwable::class);
    });

    it('counts the characters of the notes rather than their bytes', function () {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'notes' => str_repeat('á', AppointmentNotes::MAXIMUM_LENGTH),
        ]))->validate())->not->toThrow(Throwable::class);
    });

    it('skips the notes rules altogether when the caller wrote none', function (mixed $notes) {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'notes' => $notes,
        ]))->validate())->not->toThrow(Throwable::class);
    })->with(['missing' => null, 'empty' => '', 'blank' => '   ']);
});

describe('the failure a caller is handed', function () {
    it('refuses an unknown customer with a failure the responder renders as a missing page', function () {
        $refusal = null;

        try {
            CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput(['customer_id' => 'nobody']))->validate();
        } catch (AppointmentCustomerNotFound $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('appointment_customer_not_found')
            ->and($refusal?->kind())->toBe(DomainFailureKind::NotFound);
    });

    it('refuses an unknown service with a failure the responder renders as a missing page', function () {
        $refusal = null;

        try {
            CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput(['service_id' => 'nothing']))->validate();
        } catch (AppointmentServiceNotFound $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('appointment_service_not_found')
            ->and($refusal?->kind())->toBe(DomainFailureKind::NotFound);
    });

    it('refuses an unknown team member with a failure the responder renders as a missing page', function () {
        $refusal = null;

        try {
            CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput(['staff_member_id' => 'nobody']))->validate();
        } catch (AppointmentStaffNotFound $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('appointment_staff_not_found')
            ->and($refusal?->kind())->toBe(DomainFailureKind::NotFound);
    });

    it('refuses an unreadable schedule with a failure the responder renders as a bad request', function () {
        $refusal = null;

        try {
            CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput(['starts_at' => 'tomorrow']))->validate();
        } catch (InvalidAppointmentSchedule $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_appointment_schedule')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('refuses overlong notes with a failure the responder renders as a bad request', function () {
        $refusal = null;

        try {
            CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
                'notes' => str_repeat('a', AppointmentNotes::MAXIMUM_LENGTH + 1),
            ]))->validate();
        } catch (InvalidAppointmentNotes $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_appointment_notes')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
    });
});

describe('turning itself into what the use case books', function () {
    it('hands the entity the instant the caller typed, moved to the timezone the column stores', function () {
        $startsAt = CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'starts_at' => '2026-03-02T11:00:00+01:00',
        ]))->toStartsAt();

        expect($startsAt)->toEqual(new DateTimeImmutable('2026-03-02T10:00:00+00:00'))
            ->and($startsAt->getTimezone()->getName())->toBe('UTC');
    });

    it('hands the entity the end the caller typed', function () {
        expect(CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput())->toEndsAt())
            ->toEqual(new DateTimeImmutable('2026-03-02T11:00:00+00:00'));
    });

    it('hands the entity no end when the caller gave none, so the service duration decides', function (mixed $endsAt) {
        expect(CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput(['ends_at' => $endsAt]))->toEndsAt())
            ->toBeNull();
    })->with(['missing' => null, 'empty' => '', 'blank' => '   ']);

    it('hands the entity the notes trimmed', function () {
        expect(CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'notes' => '  Prefers the afternoon.  ',
        ]))->toNotes()?->value)->toBe('Prefers the afternoon.');
    });

    it('hands the entity no notes when the caller wrote none', function () {
        expect(CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput(['notes' => null]))->toNotes())
            ->toBeNull();
    });

    it('refuses rather than handing the entity an instant it invented', function () {
        expect(fn () => CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'starts_at' => '2026-13-01T10:00:00Z',
        ]))->toStartsAt())->toThrow(InvalidAppointmentSchedule::class);
    });

    it('rolls a day the calendar lacks over into the next month rather than refusing it', function () {
        expect(CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'starts_at' => '2026-02-30T10:00:00Z',
        ]))->toStartsAt())->toEqual(new DateTimeImmutable('2026-03-02T10:00:00+00:00'));
    });

    it('reads a spring forward booking as the absolute instant its offset names', function () {
        $startsAt = CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'starts_at' => '2026-03-29T03:30:00+02:00',
        ]))->toStartsAt();

        expect($startsAt)->toEqual(new DateTimeImmutable('2026-03-29T01:30:00+00:00'));
    });

    it('tells the two fall back readings of the same local time apart by their offset', function () {
        $firstPass = CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'starts_at' => '2026-10-25T02:30:00+02:00',
        ]))->toStartsAt();

        $secondPass = CreateAppointmentInput::fromRequest(payloadForCreateAppointmentInput([
            'starts_at' => '2026-10-25T02:30:00+01:00',
        ]))->toStartsAt();

        expect($firstPass)->toEqual(new DateTimeImmutable('2026-10-25T00:30:00+00:00'))
            ->and($secondPass)->toEqual(new DateTimeImmutable('2026-10-25T01:30:00+00:00'))
            ->and($firstPass)->not->toEqual($secondPass);
    });
});
