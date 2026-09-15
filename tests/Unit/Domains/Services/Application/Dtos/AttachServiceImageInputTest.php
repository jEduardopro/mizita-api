<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\AttachServiceImageInput;
use App\Domains\Services\Exceptions\ServiceImageTooLarge;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\Exceptions\UnsupportedServiceImage;
use Tests\Support\Services\ServiceFixtures;

function attachImageInput(
    string $serviceId = ServiceFixtures::SERVICE_ID,
    string $sourcePath = '/tmp/php7Xy9',
    string $fileName = 'corte.png',
    string $mimeType = 'image/png',
    int $sizeInBytes = 120_000,
): AttachServiceImageInput {
    return new AttachServiceImageInput($serviceId, $sourcePath, $fileName, $mimeType, $sizeInBytes);
}

it('accepts an upload every rule agrees with', function () {
    expect(fn () => attachImageInput()->validate())->not->toThrow(Throwable::class);
});

it('accepts every image type the collection takes', function (string $mimeType) {
    expect(fn () => attachImageInput(mimeType: $mimeType)->validate())->not->toThrow(Throwable::class);
})->with(AttachServiceImageInput::ACCEPTED_MIME_TYPES);

it('refuses an identifier that cannot be a service', function (string $serviceId) {
    expect(fn () => attachImageInput(serviceId: $serviceId)->validate())->toThrow(ServiceNotFound::class);
})->with([
    'empty' => '',
    'not a uuid' => 'corte-de-pelo',
    'a truncated uuid' => '01930000-0000-7000-8000',
    'a path traversal' => '../../etc/passwd',
]);

it('refuses an upload that carries no file behind it', function (string $sourcePath) {
    expect(fn () => attachImageInput(sourcePath: $sourcePath)->validate())
        ->toThrow(UnsupportedServiceImage::class, 'No image was offered.');
})->with(['empty' => '', 'spaces' => '   ']);

it('refuses a type outside the ones it serves', function (string $mimeType) {
    expect(fn () => attachImageInput(mimeType: $mimeType)->validate())
        ->toThrow(UnsupportedServiceImage::class);
})->with([
    'a vector' => 'image/svg+xml',
    'a gif' => 'image/gif',
    'a pdf' => 'application/pdf',
    'a script' => 'text/html',
    'uppercase' => 'IMAGE/PNG',
    'empty' => '',
]);

it('refuses an empty file', function (int $sizeInBytes) {
    expect(fn () => attachImageInput(sizeInBytes: $sizeInBytes)->validate())
        ->toThrow(UnsupportedServiceImage::class, 'No image was offered.');
})->with(['nothing' => 0, 'negative' => -1]);

it('accepts a file exactly as large as it stores', function () {
    expect(fn () => attachImageInput(sizeInBytes: AttachServiceImageInput::MAXIMUM_BYTES)->validate())
        ->not->toThrow(Throwable::class);
});

it('refuses a file larger than it stores', function () {
    expect(fn () => attachImageInput(sizeInBytes: AttachServiceImageInput::MAXIMUM_BYTES + 1)->validate())
        ->toThrow(ServiceImageTooLarge::class);
});

it('caps an upload at two megabytes', function () {
    expect(AttachServiceImageInput::MAXIMUM_BYTES)->toBe(2 * 1024 * 1024);
});

it('checks the service before the file', function () {
    expect(fn () => attachImageInput(serviceId: 'not-a-uuid', mimeType: 'application/pdf', sizeInBytes: 0)->validate())
        ->toThrow(ServiceNotFound::class);
});
