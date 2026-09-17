<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\AttachCustomerPhotoInput;
use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Domains\Customers\Exceptions\CustomerPhotoTooLarge;
use App\Domains\Customers\Exceptions\UnsupportedCustomerPhoto;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Customers\CustomerFixtures;

function attachCustomerPhotoInput(
    string $customerId = CustomerFixtures::CUSTOMER_ID,
    string $sourcePath = CustomerFixtures::PHOTO_SOURCE_PATH,
    string $fileName = CustomerFixtures::PHOTO_FILE_NAME,
    string $mimeType = CustomerFixtures::PHOTO_MIME_TYPE,
    int $sizeInBytes = CustomerFixtures::PHOTO_SIZE_IN_BYTES,
): AttachCustomerPhotoInput {
    return new AttachCustomerPhotoInput($customerId, $sourcePath, $fileName, $mimeType, $sizeInBytes);
}

it('accepts an upload every rule agrees with', function () {
    expect(fn () => attachCustomerPhotoInput()->validate())->not->toThrow(Throwable::class);
});

it('accepts every photo type the collection takes', function (string $mimeType) {
    expect(fn () => attachCustomerPhotoInput(mimeType: $mimeType)->validate())->not->toThrow(Throwable::class);
})->with(AttachCustomerPhotoInput::ACCEPTED_MIME_TYPES);

it('refuses an identifier that cannot be a customer', function (string $customerId) {
    expect(fn () => attachCustomerPhotoInput(customerId: $customerId)->validate())
        ->toThrow(CustomerNotFound::class);
})->with([
    'empty' => '',
    'not a uuid' => 'ada-lovelace',
    'a number' => '42',
    'a truncated uuid' => '01930000-0000-7000-8000',
    'a uuid with a stray character' => '01930000-0000-7000-8000-0000000000c1x',
    'a path traversal' => '../../etc/passwd',
]);

it('refuses an upload that carries no file behind it', function (string $sourcePath) {
    expect(fn () => attachCustomerPhotoInput(sourcePath: $sourcePath)->validate())
        ->toThrow(UnsupportedCustomerPhoto::class, 'No photo was offered.');
})->with(['empty' => '', 'spaces' => '   ', 'tab' => "\t"]);

it('refuses a type outside the ones it serves', function (string $mimeType) {
    expect(fn () => attachCustomerPhotoInput(mimeType: $mimeType)->validate())
        ->toThrow(UnsupportedCustomerPhoto::class);
})->with([
    'a gif' => 'image/gif',
    'a vector' => 'image/svg+xml',
    'a pdf' => 'application/pdf',
    'a script' => 'text/html',
    'uppercase' => 'IMAGE/JPEG',
    'empty' => '',
]);

it('refuses an empty file', function (int $sizeInBytes) {
    expect(fn () => attachCustomerPhotoInput(sizeInBytes: $sizeInBytes)->validate())
        ->toThrow(UnsupportedCustomerPhoto::class, 'No photo was offered.');
})->with(['nothing' => 0, 'negative' => -1]);

it('accepts a file exactly as large as it stores', function () {
    expect(fn () => attachCustomerPhotoInput(sizeInBytes: AttachCustomerPhotoInput::MAXIMUM_BYTES)->validate())
        ->not->toThrow(Throwable::class);
});

it('refuses a file one byte larger than it stores', function () {
    expect(fn () => attachCustomerPhotoInput(sizeInBytes: AttachCustomerPhotoInput::MAXIMUM_BYTES + 1)->validate())
        ->toThrow(CustomerPhotoTooLarge::class);
});

it('caps an upload at two megabytes', function () {
    expect(AttachCustomerPhotoInput::MAXIMUM_BYTES)->toBe(2 * 1024 * 1024);
});

it('checks the customer before the file, so a stranger learns nothing from what they sent', function () {
    expect(fn () => attachCustomerPhotoInput(
        customerId: 'not-a-uuid',
        sourcePath: '   ',
        mimeType: 'application/pdf',
        sizeInBytes: 0,
    )->validate())->toThrow(CustomerNotFound::class);
});

it('refuses through a failure the edge can classify on its own', function (callable $build, string $code, DomainFailureKind $kind) {
    $failure = null;

    try {
        $build()->validate();
    } catch (DomainFailure $caught) {
        $failure = $caught;
    }

    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure?->errorCode())->toBe($code)
        ->and($failure?->kind())->toBe($kind);
})->with([
    'an identifier that cannot be a customer' => [
        fn () => attachCustomerPhotoInput(customerId: 'not-a-uuid'), 'customer_not_found', DomainFailureKind::NotFound,
    ],
    'a type it does not serve' => [
        fn () => attachCustomerPhotoInput(mimeType: 'image/gif'), 'unsupported_customer_photo', DomainFailureKind::Invalid,
    ],
    'a file larger than it stores' => [
        fn () => attachCustomerPhotoInput(sizeInBytes: AttachCustomerPhotoInput::MAXIMUM_BYTES + 1), 'customer_photo_too_large', DomainFailureKind::Invalid,
    ],
]);
