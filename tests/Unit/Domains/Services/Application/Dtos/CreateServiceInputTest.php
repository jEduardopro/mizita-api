<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\CreateServiceInput;
use App\Domains\Services\Exceptions\InvalidServiceBuffer;
use App\Domains\Services\Exceptions\InvalidServiceColor;
use App\Domains\Services\Exceptions\InvalidServiceDescription;
use App\Domains\Services\Exceptions\InvalidServiceDuration;
use App\Domains\Services\Exceptions\InvalidServiceName;
use App\Domains\Services\Exceptions\InvalidServicePrice;
use App\Domains\Services\Exceptions\ServiceNameNotSluggable;
use App\Domains\Services\Exceptions\ServiceRequiresStaff;
use App\Domains\Services\Exceptions\UnknownStaffMember;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Services\ServiceFixtures;

/**
 * @return array<string, mixed>
 */
function createServicePayload(array $overrides = []): array
{
    return [
        'name' => 'Corte de pelo',
        'description' => 'Incluye lavado.',
        'duration_minutes' => 45,
        'buffer_minutes' => 10,
        'price' => '250.00',
        'color' => 'teal',
        'active' => true,
        'staff_ids' => [ServiceFixtures::STAFF_ID],
        ...$overrides,
    ];
}

describe('reading a payload', function () {
    it('assembles itself from a well formed payload', function () {
        $input = CreateServiceInput::fromRequest(createServicePayload());

        expect($input->name)->toBe('Corte de pelo')
            ->and($input->description)->toBe('Incluye lavado.')
            ->and($input->durationMinutes)->toBe(45)
            ->and($input->bufferMinutes)->toBe(10)
            ->and($input->price)->toBe('250.00')
            ->and($input->color)->toBe('teal')
            ->and($input->active)->toBeTrue()
            ->and($input->staffIds)->toBe([ServiceFixtures::STAFF_ID]);
    });

    it('survives a payload with every key missing', function () {
        $input = CreateServiceInput::fromRequest([]);

        expect($input->name)->toBe('')
            ->and($input->description)->toBeNull()
            ->and($input->durationMinutes)->toBe(0)
            ->and($input->bufferMinutes)->toBe(0)
            ->and($input->price)->toBe('')
            ->and($input->color)->toBe('')
            ->and($input->active)->toBeTrue()
            ->and($input->staffIds)->toBe([]);
    });

    it('turns a payload with every key missing into a domain failure, never a php error', function () {
        expect(fn () => CreateServiceInput::fromRequest([])->validate())
            ->toThrow(InvalidServiceName::class);
    });

    it('reads a wrongly typed value as the empty one its rules will refuse', function (array $payload, string $field, mixed $expected) {
        expect(CreateServiceInput::fromRequest($payload)->{$field})->toBe($expected);
    })->with([
        'name as an array' => [['name' => ['Corte']], 'name', ''],
        'name as a number' => [['name' => 42], 'name', ''],
        'name as null' => [['name' => null], 'name', ''],
        'description as an array' => [['description' => []], 'description', null],
        'description blank' => [['description' => '   '], 'description', null],
        'duration as a numeric string' => [['duration_minutes' => '45'], 'durationMinutes', 45],
        'duration as a word' => [['duration_minutes' => 'long'], 'durationMinutes', 0],
        'duration as an array' => [['duration_minutes' => [45]], 'durationMinutes', 0],
        'duration as a float string' => [['duration_minutes' => '45.9'], 'durationMinutes', 45],
        'buffer as a negative string' => [['buffer_minutes' => '-1'], 'bufferMinutes', -1],
        'price as a number' => [['price' => 250], 'price', ''],
        'color as an array' => [['color' => ['teal']], 'color', ''],
        'staff as a string' => [['staff_ids' => ServiceFixtures::STAFF_ID], 'staffIds', []],
        'staff as null' => [['staff_ids' => null], 'staffIds', []],
    ]);

    it('reads a staff entry that is not a string as an empty one its rules will refuse', function () {
        $input = CreateServiceInput::fromRequest(createServicePayload([
            'staff_ids' => [42, null, ServiceFixtures::STAFF_ID],
        ]));

        expect($input->staffIds)->toBe(['', '', ServiceFixtures::STAFF_ID])
            ->and(fn () => $input->validate())->toThrow(UnknownStaffMember::class);
    });

    it('reads a staff list that arrived keyed as a plain list', function () {
        $input = CreateServiceInput::fromRequest([
            'staff_ids' => [3 => ServiceFixtures::STAFF_ID, 7 => ServiceFixtures::SECOND_STAFF_ID],
        ]);

        expect($input->staffIds)->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);
    });

    it('defaults a missing visibility to visible', function () {
        expect(CreateServiceInput::fromRequest(['active' => null])->active)->toBeTrue();
    });

    it('reads the visibility the payload states', function (mixed $value, bool $expected) {
        expect(CreateServiceInput::fromRequest(['active' => $value])->active)->toBe($expected);
    })->with([
        'true' => [true, true],
        'false' => [false, false],
        'one' => [1, true],
        'zero' => [0, false],
        'the string zero' => ['0', false],
    ]);
});

describe('validating', function () {
    it('accepts a payload every rule agrees with', function () {
        expect(fn () => CreateServiceInput::fromRequest(createServicePayload())->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts a service with no description, no buffer and no price', function () {
        expect(fn () => CreateServiceInput::fromRequest(createServicePayload([
            'description' => null,
            'price' => '0',
            'buffer_minutes' => 0,
        ]))->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a service nobody was selected to perform', function (mixed $selection) {
        expect(fn () => CreateServiceInput::fromRequest(createServicePayload(['staff_ids' => $selection]))->validate())
            ->toThrow(ServiceRequiresStaff::class);
    })->with([
        'an empty selection' => [[]],
        'a selection that is not a list' => ['all'],
        'a selection that is null' => [null],
    ]);

    it('refuses a payload that never mentions the staff at all', function () {
        $payload = createServicePayload();
        unset($payload['staff_ids']);

        expect(fn () => CreateServiceInput::fromRequest($payload)->validate())
            ->toThrow(ServiceRequiresStaff::class);
    });

    it('refuses an empty selection with a failure the client can act on', function () {
        try {
            CreateServiceInput::fromRequest(createServicePayload(['staff_ids' => []]))->validate();
        } catch (Throwable $failure) {
            expect($failure)->toBeInstanceOf(DomainFailure::class)
                ->and($failure->errorCode())->toBe('service_requires_staff')
                ->and($failure->kind())->toBe(DomainFailureKind::Invalid);

            return;
        }

        throw new RuntimeException('The empty selection was accepted.');
    });

    it('refuses what a form request would have refused', function (array $overrides, string $exception) {
        expect(fn () => CreateServiceInput::fromRequest(createServicePayload($overrides))->validate())
            ->toThrow($exception);
    })->with([
        'missing name' => [['name' => null], InvalidServiceName::class],
        'blank name' => [['name' => '   '], InvalidServiceName::class],
        'name of one character' => [['name' => 'A'], InvalidServiceName::class],
        'name too long' => [['name' => str_repeat('a', 121)], InvalidServiceName::class],
        'name with nothing to slug' => [['name' => '!!!!'], ServiceNameNotSluggable::class],
        'description too long' => [['description' => str_repeat('a', 2001)], InvalidServiceDescription::class],
        'no duration' => [['duration_minutes' => null], InvalidServiceDuration::class],
        'duration of zero' => [['duration_minutes' => 0], InvalidServiceDuration::class],
        'duration beyond a day' => [['duration_minutes' => 1441], InvalidServiceDuration::class],
        'negative buffer' => [['buffer_minutes' => -1], InvalidServiceBuffer::class],
        'buffer beyond a day' => [['buffer_minutes' => 1441], InvalidServiceBuffer::class],
        'missing price' => [['price' => null], InvalidServicePrice::class],
        'negative price' => [['price' => '-1'], InvalidServicePrice::class],
        'price in scientific notation' => [['price' => '1e3'], InvalidServicePrice::class],
        'price with three decimals' => [['price' => '10.001'], InvalidServicePrice::class],
        'missing colour' => [['color' => null], InvalidServiceColor::class],
        'colour outside the palette' => [['color' => 'rose'], InvalidServiceColor::class],
        'a hex colour' => [['color' => '#ff0000'], InvalidServiceColor::class],
        'a staff id that is not a uuid' => [['staff_ids' => ['not-a-uuid']], UnknownStaffMember::class],
        'a blank staff id' => [['staff_ids' => ['']], UnknownStaffMember::class],
    ]);

    it('refuses more staff than a service may carry', function () {
        $staffIds = array_map(
            static fn (int $n): string => sprintf('01930000-0000-7000-8000-%012d', $n),
            range(1, 51),
        );

        expect(fn () => CreateServiceInput::fromRequest(createServicePayload(['staff_ids' => $staffIds]))->validate())
            ->toThrow(UnknownStaffMember::class);
    });

    it('accepts a staff id whatever the case of its hexadecimal', function () {
        expect(fn () => CreateServiceInput::fromRequest(createServicePayload([
            'staff_ids' => [strtoupper(ServiceFixtures::STAFF_ID)],
        ]))->validate())->not->toThrow(Throwable::class);
    });

    it('refuses with a failure the client can act on', function () {
        try {
            CreateServiceInput::fromRequest([])->validate();
        } catch (Throwable $failure) {
            expect($failure)->toBeInstanceOf(DomainFailure::class)
                ->and($failure->errorCode())->toBe('invalid_service_name');

            return;
        }

        throw new RuntimeException('The empty payload was accepted.');
    });
});
