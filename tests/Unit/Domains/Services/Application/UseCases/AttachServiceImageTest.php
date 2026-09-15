<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\AttachServiceImageInput;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Application\UseCases\AttachServiceImage;
use App\Domains\Services\Services\BookingLinks;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\FakeBusinessProfile;
use Tests\Support\Services\FakeServiceImages;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\FakeStaffDirectory;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->services = new FakeServiceRepository;
    $this->images = new FakeServiceImages;
    $this->staff = FakeStaffDirectory::of(FakeBusinessContext::BUSINESS_ID, [
        ServiceFixtures::STAFF_ID => 'Ada Lovelace',
    ]);

    $this->useCase = new AttachServiceImage(
        $this->services,
        $this->images,
        new ServicePresenter(
            $this->staff,
            $this->images,
            new FakeBusinessProfile,
            new BookingLinks(ServiceFixtures::BASE_URL),
        ),
        new FakeBusinessContext,
    );

    $this->attach = fn (
        string $serviceId = ServiceFixtures::SERVICE_ID,
        string $sourcePath = '/tmp/php7Xy9',
        string $fileName = 'corte.png',
        string $mimeType = 'image/png',
        int $sizeInBytes = 120_000,
    ) => $this->useCase->handle(
        new AttachServiceImageInput($serviceId, $sourcePath, $fileName, $mimeType, $sizeInBytes),
    );
});

it('hands the image to the port and answers with the service carrying it', function () {
    $this->services->store(ServiceFixtures::service());

    $data = ($this->attach)()->value();

    expect($this->images->replaced)->toBe([[
        'serviceId' => ServiceFixtures::SERVICE_ID,
        'sourcePath' => '/tmp/php7Xy9',
        'fileName' => 'corte.png',
    ]])->and($data->id)->toBe(ServiceFixtures::SERVICE_ID)
        ->and($data->imageUrl)->toBe('https://cdn.mizita.test/services/'.ServiceFixtures::SERVICE_ID.'/corte.png');
});

it('replaces the image a service already had', function () {
    $this->services->store(ServiceFixtures::service());
    ($this->attach)();

    $data = ($this->attach)(fileName: 'nuevo.webp', mimeType: 'image/webp')->value();

    expect($this->images->replaced)->toHaveCount(2)
        ->and($data->imageUrl)->toEndWith('/nuevo.webp');
});

it('looks the service up under the business in context', function () {
    $this->services->store(ServiceFixtures::service());

    ($this->attach)();

    expect($this->services->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('does not touch the image of a service that belongs to another business', function () {
    $this->services->store(ServiceFixtures::service(businessId: ServiceFixtures::OTHER_BUSINESS_ID));

    $response = ($this->attach)();

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('service_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->images->replaced)->toBe([]);
});

it('answers not found for a service nobody has', function () {
    expect(($this->attach)()->error()->code)->toBe('service_not_found')
        ->and($this->images->replaced)->toBe([]);
});

it('refuses an upload its own rules refuse, without touching the repository or the file', function (array $overrides, string $code, DomainFailureKind $kind) {
    $this->services->store(ServiceFixtures::service());

    $response = ($this->attach)(...$overrides);

    expect($response->error()->code)->toBe($code)
        ->and($response->error()->kind)->toBe($kind)
        ->and($this->images->replaced)->toBe([])
        ->and($this->services->businessIdsSeen)->toBe([]);
})->with([
    'an identifier that cannot be a service' => [
        ['serviceId' => 'not-a-uuid'], 'service_not_found', DomainFailureKind::NotFound,
    ],
    'no file behind the upload' => [
        ['sourcePath' => '   '], 'unsupported_service_image', DomainFailureKind::Invalid,
    ],
    'an empty file' => [
        ['sizeInBytes' => 0], 'unsupported_service_image', DomainFailureKind::Invalid,
    ],
    'a type it does not serve' => [
        ['mimeType' => 'image/svg+xml'], 'unsupported_service_image', DomainFailureKind::Invalid,
    ],
    'a file larger than it stores' => [
        ['sizeInBytes' => AttachServiceImageInput::MAXIMUM_BYTES + 1], 'service_image_too_large', DomainFailureKind::Invalid,
    ],
]);
