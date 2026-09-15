<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Infrastructure\Http\Resources\ServiceResource;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Domains\Services\ValueObjects\StaffMemberSnapshot;
use Tests\Support\Services\ServiceFixtures;
use Tests\TestCase;

uses(TestCase::class);

function serviceResourceData(
    ?string $description = 'Incluye lavado.',
    ServiceColor $color = ServiceColor::Teal,
    bool $active = true,
    ?string $imageUrl = 'https://cdn.mizita.test/corte.png',
    array $staff = [],
): ServiceData {
    return new ServiceData(
        id: ServiceFixtures::SERVICE_ID,
        name: ServiceFixtures::NAME,
        slug: ServiceFixtures::SLUG,
        description: $description,
        durationMinutes: 45,
        bufferMinutes: 10,
        price: '250.00',
        color: $color,
        active: $active,
        imageUrl: $imageUrl,
        bookingUrl: 'https://mizita.test/b/ada-salon/corte-de-pelo',
        staff: $staff,
        createdAt: ServiceFixtures::now(),
    );
}

/**
 * @return array<string, mixed>
 */
function serializedService(ServiceData $service): array
{
    return (array) ServiceResource::make($service)->response()->getData(true)['data'];
}

it('serializes exactly the thirteen fields the client transcribed', function () {
    expect(serializedService(serviceResourceData()))->toBe([
        'id' => ServiceFixtures::SERVICE_ID,
        'name' => 'Corte de pelo',
        'slug' => 'corte-de-pelo',
        'description' => 'Incluye lavado.',
        'duration_minutes' => 45,
        'buffer_minutes' => 10,
        'price' => '250.00',
        'color' => 'teal',
        'active' => true,
        'image_url' => 'https://cdn.mizita.test/corte.png',
        'booking_url' => 'https://mizita.test/b/ada-salon/corte-de-pelo',
        'staff' => [],
        'created_at' => '2026-01-01T12:00:00+00:00',
    ]);
});

it('never puts the business a service belongs to on the wire', function () {
    expect(serializedService(serviceResourceData()))->not->toHaveKey('business_id')
        ->and(serializedService(serviceResourceData()))->not->toHaveKey('businessId');
});

it('sends the colour as the key the front end maps to a class', function () {
    expect(serializedService(serviceResourceData(color: ServiceColor::Sand))['color'])->toBe('sand');
});

it('sends the price as the string it stores, never as a number', function () {
    expect(serializedService(serviceResourceData())['price'])->toBeString()->toBe('250.00');
});

it('sends nothing for a service with no description and no image', function () {
    $service = serializedService(serviceResourceData(description: null, imageUrl: null));

    expect($service['description'])->toBeNull()
        ->and($service['image_url'])->toBeNull();
});

it('sends a hidden service as hidden', function () {
    expect(serializedService(serviceResourceData(active: false))['active'])->toBeFalse();
});

it('sends each staff member as an identity and a name, and nothing more', function () {
    $service = serializedService(serviceResourceData(staff: [
        new StaffMemberSnapshot(ServiceFixtures::STAFF_ID, 'Ada Lovelace'),
        new StaffMemberSnapshot(ServiceFixtures::SECOND_STAFF_ID, 'Grace Hopper'),
    ]));

    expect($service['staff'])->toBe([
        ['id' => ServiceFixtures::STAFF_ID, 'name' => 'Ada Lovelace'],
        ['id' => ServiceFixtures::SECOND_STAFF_ID, 'name' => 'Grace Hopper'],
    ]);
});

it('sends the date as the format the whole api speaks', function () {
    expect(serializedService(serviceResourceData())['created_at'])->toBe('2026-01-01T12:00:00+00:00');
});
