<?php

declare(strict_types=1);

use App\Domains\Services\Entities\Service;
use App\Domains\Services\Infrastructure\Eloquent\Mappers\ServiceMapper;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use Illuminate\Database\Eloquent\Collection;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\ServiceFixtures;

const SERVICE_MAPPER_BUSINESS_KEY = 42;

/**
 * @param  array<string, mixed>  $overrides
 * @param  list<string>  $staffUuids
 */
function serviceRow(array $overrides = [], array $staffUuids = [ServiceFixtures::STAFF_ID]): ServiceModel
{
    $model = new ServiceModel;

    $model->setRawAttributes([
        'id' => 7,
        'uuid' => ServiceFixtures::SERVICE_ID,
        'business_id' => SERVICE_MAPPER_BUSINESS_KEY,
        'name' => ServiceFixtures::NAME,
        'slug' => ServiceFixtures::SLUG,
        'description' => 'Incluye lavado.',
        'duration_minutes' => 45,
        'buffer_minutes' => 10,
        'price' => '250.00',
        'color' => ServiceColor::Teal->value,
        'active' => true,
        'created_at' => ServiceFixtures::now(),
        ...$overrides,
    ], true);

    $model->setRelation('staffMembers', new Collection(array_map(
        static function (string $uuid, int $key): StaffMemberModel {
            $staffMember = new StaffMemberModel;
            $staffMember->setRawAttributes(['id' => $key + 1, 'uuid' => $uuid], true);

            return $staffMember;
        },
        $staffUuids,
        array_keys($staffUuids),
    )));

    return $model;
}

describe('reading a row', function () {
    it('restores every value the row carries', function () {
        $service = (new ServiceMapper)->toEntity(serviceRow(), FakeBusinessContext::BUSINESS_ID);

        expect($service)->toBeInstanceOf(Service::class)
            ->and($service->id)->toBe(ServiceFixtures::SERVICE_ID)
            ->and($service->name())->toBe(ServiceFixtures::NAME)
            ->and($service->slug())->toBe(ServiceFixtures::SLUG)
            ->and($service->description())->toBe('Incluye lavado.')
            ->and($service->durationMinutes())->toBe(45)
            ->and($service->bufferMinutes())->toBe(10)
            ->and($service->price())->toBe('250.00')
            ->and($service->color())->toBe(ServiceColor::Teal)
            ->and($service->isActive())->toBeTrue()
            ->and($service->staffIds())->toBe([ServiceFixtures::STAFF_ID])
            ->and($service->createdAt)->toEqual(ServiceFixtures::now());
    });

    it('takes the business as the uuid it was handed, never the key the row holds', function () {
        $service = (new ServiceMapper)->toEntity(serviceRow(), FakeBusinessContext::BUSINESS_ID);

        expect($service->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($service->businessId)->not->toBe((string) SERVICE_MAPPER_BUSINESS_KEY);
    });

    it('reads the staff by their uuid, never by the pivot key', function () {
        $service = (new ServiceMapper)->toEntity(
            serviceRow(staffUuids: [ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]),
            FakeBusinessContext::BUSINESS_ID,
        );

        expect($service->staffIds())->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);
    });

    it('restores a service nobody is assigned to', function () {
        $service = (new ServiceMapper)->toEntity(serviceRow(staffUuids: []), FakeBusinessContext::BUSINESS_ID);

        expect($service->staffIds())->toBe([]);
    });

    it('restores a service with no description', function () {
        $service = (new ServiceMapper)->toEntity(
            serviceRow(['description' => null]),
            FakeBusinessContext::BUSINESS_ID,
        );

        expect($service->description())->toBeNull();
    });

    it('restores a hidden service', function () {
        $service = (new ServiceMapper)->toEntity(serviceRow(['active' => false]), FakeBusinessContext::BUSINESS_ID);

        expect($service->isActive())->toBeFalse();
    });

    it('restores a free service without turning its price into a number', function () {
        $service = (new ServiceMapper)->toEntity(serviceRow(['price' => '0.00']), FakeBusinessContext::BUSINESS_ID);

        expect($service->price())->toBe('0.00')->toBeString();
    });

    it('restores a row without holding it to the invariants creation enforces', function () {
        $service = (new ServiceMapper)->toEntity(
            serviceRow(['name' => '', 'slug' => 'NOT a slug']),
            FakeBusinessContext::BUSINESS_ID,
        );

        expect($service->name())->toBe('')
            ->and($service->slug())->toBe('NOT a slug');
    });
});

describe('writing a row', function () {
    it('writes the business as the key it was handed, never as the uuid the entity carries', function () {
        $attributes = (new ServiceMapper)->toAttributes(ServiceFixtures::service(), SERVICE_MAPPER_BUSINESS_KEY);

        expect($attributes['business_id'])->toBe(SERVICE_MAPPER_BUSINESS_KEY)
            ->toBeInt()
            ->and($attributes['business_id'])->not->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('writes exactly the columns the row owns', function () {
        $attributes = (new ServiceMapper)->toAttributes(ServiceFixtures::service(), SERVICE_MAPPER_BUSINESS_KEY);

        expect($attributes)->toBe([
            'uuid' => ServiceFixtures::SERVICE_ID,
            'business_id' => SERVICE_MAPPER_BUSINESS_KEY,
            'name' => ServiceFixtures::NAME,
            'slug' => ServiceFixtures::SLUG,
            'description' => 'Incluye lavado.',
            'duration_minutes' => 45,
            'buffer_minutes' => 10,
            'price' => '250.00',
            'color' => 'teal',
            'active' => true,
        ]);
    });

    it('writes the colour as the value the column stores, not the case', function () {
        $attributes = (new ServiceMapper)->toAttributes(
            ServiceFixtures::service(color: ServiceColor::Sand),
            SERVICE_MAPPER_BUSINESS_KEY,
        );

        expect($attributes['color'])->toBe('sand')->toBeString();
    });

    it('leaves the staff to the pivot instead of writing them on the row', function () {
        $attributes = (new ServiceMapper)->toAttributes(
            ServiceFixtures::service(staffIds: [ServiceFixtures::STAFF_ID]),
            SERVICE_MAPPER_BUSINESS_KEY,
        );

        expect($attributes)->not->toHaveKey('staff_ids')
            ->and($attributes)->not->toHaveKey('staffIds');
    });

    it('leaves the identity and the timestamps to the database', function () {
        $attributes = (new ServiceMapper)->toAttributes(ServiceFixtures::service(), SERVICE_MAPPER_BUSINESS_KEY);

        expect($attributes)->not->toHaveKey('id')
            ->and($attributes)->not->toHaveKey('created_at')
            ->and($attributes)->not->toHaveKey('updated_at')
            ->and($attributes)->not->toHaveKey('deleted_at');
    });
});

it('carries a service through both directions unchanged', function () {
    $mapper = new ServiceMapper;

    $attributes = $mapper->toAttributes(ServiceFixtures::service(), SERVICE_MAPPER_BUSINESS_KEY);
    $restored = $mapper->toEntity(
        serviceRow([...$attributes, 'created_at' => ServiceFixtures::now()]),
        FakeBusinessContext::BUSINESS_ID,
    );

    expect($restored->id)->toBe(ServiceFixtures::SERVICE_ID)
        ->and($restored->name())->toBe(ServiceFixtures::NAME)
        ->and($restored->slug())->toBe(ServiceFixtures::SLUG)
        ->and($restored->price())->toBe('250.00')
        ->and($restored->color())->toBe(ServiceColor::Teal)
        ->and($restored->isActive())->toBeTrue();
});
