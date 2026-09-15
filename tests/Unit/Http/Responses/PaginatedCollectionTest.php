<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Infrastructure\Http\Resources\ServiceResource;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Http\Responses\ApiResponder;
use App\Http\Responses\PaginatedCollection;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Services\ServiceFixtures;
use Tests\TestCase;

uses(TestCase::class);

function pagedService(string $id, string $name, string $slug): ServiceData
{
    return new ServiceData(
        id: $id,
        name: $name,
        slug: $slug,
        description: null,
        durationMinutes: 45,
        bufferMinutes: 10,
        price: '250.00',
        color: ServiceColor::Teal,
        active: true,
        imageUrl: null,
        bookingUrl: 'https://mizita.test/b/ada-salon/'.$slug,
        staff: [],
        createdAt: ServiceFixtures::now(),
    );
}

/**
 * @param  list<ServiceData>  $services
 * @return Paginated<ServiceData>
 */
function pageOf(array $services, int $total, int $page = 1, int $perPage = 20): Paginated
{
    return Paginated::of($services, $total, Pagination::of($page, $perPage));
}

/**
 * @param  Paginated<ServiceData>  $page
 * @return array<string, mixed>
 */
function renderedPage(Paginated $page): array
{
    return (array) PaginatedCollection::of($page, ServiceResource::class)->response()->getData(true);
}

beforeEach(function () {
    $this->services = [
        pagedService(ServiceFixtures::SERVICE_ID, 'Corte de pelo', 'corte-de-pelo'),
        pagedService(ServiceFixtures::SECOND_SERVICE_ID, 'Barba', 'barba'),
    ];
});

it('renders the rows through the resource it collects', function () {
    $body = renderedPage(pageOf($this->services, 2));

    expect($body['data'])->toHaveCount(2)
        ->and($body['data'][0]['id'])->toBe(ServiceFixtures::SERVICE_ID)
        ->and($body['data'][0]['name'])->toBe('Corte de pelo')
        ->and($body['data'][1]['name'])->toBe('Barba')
        ->and(array_keys($body['data'][0]))->toContain('booking_url');
});

it('renders the meta the table and the infinite list navigate by', function () {
    $body = renderedPage(pageOf($this->services, 42, 2, 25));

    expect($body['meta'])->toBe([
        'current_page' => 2,
        'per_page' => 25,
        'total' => 42,
        'last_page' => 2,
    ]);
});

it('carries nothing beyond the rows and the meta', function () {
    expect(array_keys(renderedPage(pageOf($this->services, 2))))->toBe(['data', 'meta']);
});

it('renders an empty page as empty rows with honest meta, never as an absence', function () {
    $body = renderedPage(pageOf([], 0, 9, 20));

    expect($body['data'])->toBe([])
        ->and($body['meta'])->toBe([
            'current_page' => 9,
            'per_page' => 20,
            'total' => 0,
            'last_page' => 1,
        ]);
});

it('reports the pagination it was clamped to, not the one that was asked for', function () {
    $body = renderedPage(pageOf($this->services, 2, 0, 9999));

    expect($body['meta']['current_page'])->toBe(1)
        ->and($body['meta']['per_page'])->toBe(Pagination::MAXIMUM_PER_PAGE);
});

it('lets the meta and a warning ride on the same body', function () {
    app()->setLocale('en');
    $translator = app('translator');
    $translator->get('messages.warnings', [], 'en');
    $translator->addLines(['messages.warnings.image_not_saved' => 'We could not save the image.'], 'en');

    $responder = new ApiResponder(Mockery::spy(LoggerInterface::class));

    $response = $responder->success(
        UseCaseResponse::success(pageOf($this->services, 2))->addWarning('image_not_saved'),
        PaginatedCollection::of(pageOf($this->services, 2), ServiceResource::class),
        Response::HTTP_OK,
    );

    $body = (array) $response->getData(true);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(array_keys($body))->toBe(['data', 'meta', 'warnings'])
        ->and($body['meta']['total'])->toBe(2)
        ->and($body['warnings'])->toBe([
            ['code' => 'image_not_saved', 'message' => 'We could not save the image.'],
        ]);
});
