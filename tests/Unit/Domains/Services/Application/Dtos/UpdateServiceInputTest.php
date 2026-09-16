<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\UpdateServiceInput;
use App\Domains\Services\Exceptions\InvalidServiceBuffer;
use App\Domains\Services\Exceptions\InvalidServiceColor;
use App\Domains\Services\Exceptions\InvalidServiceDescription;
use App\Domains\Services\Exceptions\InvalidServiceDuration;
use App\Domains\Services\Exceptions\InvalidServiceName;
use App\Domains\Services\Exceptions\InvalidServicePrice;
use App\Domains\Services\Exceptions\ServiceNameNotSluggable;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\Exceptions\ServiceRequiresStaff;
use App\Domains\Services\Exceptions\UnknownStaffMember;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Services\ServiceFixtures;

/**
 * @return array<string, mixed>
 */
function updateServicePayload(array $overrides = []): array
{
    return [
        'name' => 'Corte premium',
        'description' => 'Incluye lavado.',
        'duration_minutes' => 60,
        'buffer_minutes' => 0,
        'price' => '300',
        'color' => 'sand',
        'active' => false,
        'staff_ids' => [ServiceFixtures::STAFF_ID],
        ...$overrides,
    ];
}

describe('reading a payload', function () {
    it('assembles itself from the payload and the identifier in the url', function () {
        $input = UpdateServiceInput::fromRequest(updateServicePayload(), ServiceFixtures::SERVICE_ID);

        expect($input->serviceId)->toBe(ServiceFixtures::SERVICE_ID)
            ->and($input->name)->toBe('Corte premium')
            ->and($input->description)->toBe('Incluye lavado.')
            ->and($input->durationMinutes)->toBe(60)
            ->and($input->bufferMinutes)->toBe(0)
            ->and($input->price)->toBe('300')
            ->and($input->color)->toBe('sand')
            ->and($input->active)->toBeFalse()
            ->and($input->staffIds)->toBe([ServiceFixtures::STAFF_ID]);
    });

    it('takes the identifier from the url and never from a key the client could set', function () {
        $input = UpdateServiceInput::fromRequest(updateServicePayload([
            'id' => ServiceFixtures::SECOND_SERVICE_ID,
            'service_id' => ServiceFixtures::SECOND_SERVICE_ID,
            'serviceId' => ServiceFixtures::SECOND_SERVICE_ID,
        ]), ServiceFixtures::SERVICE_ID);

        expect($input->serviceId)->toBe(ServiceFixtures::SERVICE_ID);
    });

    it('survives a payload with every key missing', function () {
        $input = UpdateServiceInput::fromRequest([], ServiceFixtures::SERVICE_ID);

        expect($input->name)->toBe('')
            ->and($input->description)->toBeNull()
            ->and($input->durationMinutes)->toBe(0)
            ->and($input->bufferMinutes)->toBe(0)
            ->and($input->price)->toBe('')
            ->and($input->color)->toBe('')
            ->and($input->active)->toBeTrue()
            ->and($input->staffIds)->toBe([]);
    });

    it('reads a wrongly typed value as the empty one its rules will refuse', function (array $payload, string $field, mixed $expected) {
        expect(UpdateServiceInput::fromRequest($payload, ServiceFixtures::SERVICE_ID)->{$field})->toBe($expected);
    })->with([
        'name as an array' => [['name' => ['Corte']], 'name', ''],
        'description blank' => [['description' => "\n"], 'description', null],
        'duration as a numeric string' => [['duration_minutes' => '60'], 'durationMinutes', 60],
        'duration as a word' => [['duration_minutes' => 'long'], 'durationMinutes', 0],
        'price as a float' => [['price' => 300.5], 'price', ''],
        'staff as a string' => [['staff_ids' => 'all'], 'staffIds', []],
    ]);
});

describe('validating', function () {
    it('accepts a payload every rule agrees with', function () {
        expect(fn () => UpdateServiceInput::fromRequest(updateServicePayload(), ServiceFixtures::SERVICE_ID)->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses an identifier that cannot be a service, without saying more', function (string $serviceId) {
        expect(fn () => UpdateServiceInput::fromRequest(updateServicePayload(), $serviceId)->validate())
            ->toThrow(ServiceNotFound::class);
    })->with([
        'empty' => '',
        'not a uuid' => 'corte-de-pelo',
        'an integer key' => '42',
        'a truncated uuid' => '01930000-0000-7000-8000',
        'a uuid with a trailing newline' => ServiceFixtures::SERVICE_ID."\n",
    ]);

    it('checks the identifier before anything the body carries', function () {
        expect(fn () => UpdateServiceInput::fromRequest([], 'not-a-uuid')->validate())
            ->toThrow(ServiceNotFound::class);
    });

    it('refuses what a form request would have refused', function (array $overrides, string $exception) {
        expect(fn () => UpdateServiceInput::fromRequest(
            updateServicePayload($overrides),
            ServiceFixtures::SERVICE_ID,
        )->validate())->toThrow($exception);
    })->with([
        'missing name' => [['name' => null], InvalidServiceName::class],
        'blank name' => [['name' => '   '], InvalidServiceName::class],
        'name of one character' => [['name' => 'A'], InvalidServiceName::class],
        'name too long' => [['name' => str_repeat('a', 121)], InvalidServiceName::class],
        'name with nothing to slug' => [['name' => '???'], ServiceNameNotSluggable::class],
        'description too long' => [['description' => str_repeat('a', 2001)], InvalidServiceDescription::class],
        'duration of zero' => [['duration_minutes' => 0], InvalidServiceDuration::class],
        'duration beyond a day' => [['duration_minutes' => 1441], InvalidServiceDuration::class],
        'negative buffer' => [['buffer_minutes' => -1], InvalidServiceBuffer::class],
        'missing price' => [['price' => null], InvalidServicePrice::class],
        'negative price' => [['price' => '-0.01'], InvalidServicePrice::class],
        'price in scientific notation' => [['price' => '1e3'], InvalidServicePrice::class],
        'colour outside the palette' => [['color' => 'rose'], InvalidServiceColor::class],
        'a staff id that is not a uuid' => [['staff_ids' => ['staff-1']], UnknownStaffMember::class],
    ]);

    it('refuses more staff than a service may carry', function () {
        $staffIds = array_map(
            static fn (int $n): string => sprintf('01930000-0000-7000-8000-%012d', $n),
            range(1, 51),
        );

        expect(fn () => UpdateServiceInput::fromRequest(
            updateServicePayload(['staff_ids' => $staffIds]),
            ServiceFixtures::SERVICE_ID,
        )->validate())->toThrow(UnknownStaffMember::class);
    });

    it('refuses to leave a service with nobody to perform it', function (mixed $selection) {
        expect(fn () => UpdateServiceInput::fromRequest(
            updateServicePayload(['staff_ids' => $selection]),
            ServiceFixtures::SERVICE_ID,
        )->validate())->toThrow(ServiceRequiresStaff::class);
    })->with([
        'an empty selection' => [[]],
        'a selection that is not a list' => ['all'],
        'a selection that is null' => [null],
    ]);

    it('refuses a payload that omits the staff instead of wiping the team it names nothing about', function () {
        $payload = updateServicePayload();
        unset($payload['staff_ids']);

        expect(fn () => UpdateServiceInput::fromRequest($payload, ServiceFixtures::SERVICE_ID)->validate())
            ->toThrow(ServiceRequiresStaff::class);
    });

    it('refuses an empty selection with a failure the client can act on', function () {
        try {
            UpdateServiceInput::fromRequest(
                updateServicePayload(['staff_ids' => []]),
                ServiceFixtures::SERVICE_ID,
            )->validate();
        } catch (Throwable $failure) {
            expect($failure)->toBeInstanceOf(DomainFailure::class)
                ->and($failure->errorCode())->toBe('service_requires_staff')
                ->and($failure->kind())->toBe(DomainFailureKind::Invalid);

            return;
        }

        throw new RuntimeException('The empty selection was accepted.');
    });
});
