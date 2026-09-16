<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\BusinessData;
use App\Domains\Businesses\Infrastructure\Http\Resources\BusinessResource;
use Tests\TestCase;

uses(TestCase::class);

function businessData(
    string $id = 'business-uuid',
    string $name = 'Ada Salon',
    string $slug = 'ada-salon',
    string $timezone = 'Europe/Madrid',
    string $industryId = 'industry-uuid',
    ?string $contactEmail = 'hola@ada-salon.com',
    ?string $about = 'Cortes y color desde 2019.',
    string $currency = 'MXN',
    string $createdAt = '2026-01-01T12:00:00+00:00',
): BusinessData {
    return new BusinessData(
        id: $id,
        name: $name,
        slug: $slug,
        timezone: $timezone,
        industryId: $industryId,
        contactEmail: $contactEmail,
        about: $about,
        currency: $currency,
        createdAt: new DateTimeImmutable($createdAt),
    );
}

/**
 * @return array<string, mixed>
 */
function serializedBusiness(BusinessData $business): array
{
    return (array) BusinessResource::make($business)->response()->getData(true)['data'];
}

it('serializes exactly the keys the client contract declares', function () {
    expect(serializedBusiness(businessData()))->toBe([
        'id' => 'business-uuid',
        'name' => 'Ada Salon',
        'slug' => 'ada-salon',
        'timezone' => 'Europe/Madrid',
        'industry_id' => 'industry-uuid',
        'contact_email' => 'hola@ada-salon.com',
        'about' => 'Cortes y color desde 2019.',
        'currency_code' => 'MXN',
        'created_at' => '2026-01-01T12:00:00+00:00',
    ]);
});

it('wraps the payload in the data envelope', function () {
    expect(BusinessResource::make(businessData())->response()->getData(true))
        ->toBe(['data' => [
            'id' => 'business-uuid',
            'name' => 'Ada Salon',
            'slug' => 'ada-salon',
            'timezone' => 'Europe/Madrid',
            'industry_id' => 'industry-uuid',
            'contact_email' => 'hola@ada-salon.com',
            'about' => 'Cortes y color desde 2019.',
            'currency_code' => 'MXN',
            'created_at' => '2026-01-01T12:00:00+00:00',
        ]]);
});

it('exposes the uuid as the id, never an internal key', function () {
    expect(serializedBusiness(businessData(id: '01930000-0000-7000-8000-000000000001'))['id'])
        ->toBe('01930000-0000-7000-8000-000000000001')
        ->toBeString();
});

it('never serializes business_id', function () {
    expect(serializedBusiness(businessData()))->not->toHaveKey('business_id');
});

it('names the currency column as the client reads it, not as the DTO names it', function () {
    expect(serializedBusiness(businessData(currency: 'USD')))
        ->toHaveKey('currency_code')
        ->and(serializedBusiness(businessData(currency: 'USD'))['currency_code'])->toBe('USD')
        ->and(serializedBusiness(businessData()))->not->toHaveKey('currency');
});

it('sends null for a business that filled neither optional field', function () {
    $serialized = serializedBusiness(businessData(contactEmail: null, about: null));

    expect($serialized['contact_email'])->toBeNull()
        ->and($serialized['about'])->toBeNull()
        ->and($serialized)->toHaveKeys(['contact_email', 'about']);
});

it('formats the creation instant as DATE_ATOM, keeping the offset it carries', function (string $createdAt, string $expected) {
    expect(serializedBusiness(businessData(createdAt: $createdAt))['created_at'])->toBe($expected);
})->with([
    'utc' => ['2026-01-01T12:00:00+00:00', '2026-01-01T12:00:00+00:00'],
    'winter in madrid' => ['2026-01-01T13:00:00+01:00', '2026-01-01T13:00:00+01:00'],
    'summer in madrid' => ['2026-07-01T14:00:00+02:00', '2026-07-01T14:00:00+02:00'],
]);

it('keeps unicode in the name untouched', function () {
    expect(serializedBusiness(businessData(name: 'Peluquería Ámbar'))['name'])->toBe('Peluquería Ámbar');
});

it('keeps unicode and line breaks in the description untouched', function () {
    $about = "Barbería Ñandú\nDesde 2019.";

    expect(serializedBusiness(businessData(about: $about))['about'])->toBe($about);
});
