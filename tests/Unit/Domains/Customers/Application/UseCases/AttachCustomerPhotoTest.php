<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\AttachCustomerPhotoInput;
use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Application\UseCases\AttachCustomerPhoto;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Customers\CustomerFixtures;
use Tests\Support\Customers\FakeCustomerAddressBook;
use Tests\Support\Customers\FakeCustomerPhoneBook;
use Tests\Support\Customers\FakeCustomerPhotos;
use Tests\Support\Customers\FakeCustomerRepository;
use Tests\Support\FakeBusinessContext;
use Tests\Support\PhoneNumbers;

beforeEach(function () {
    $this->customers = new FakeCustomerRepository;
    $this->phones = new FakeCustomerPhoneBook;
    $this->addresses = new FakeCustomerAddressBook;
    $this->photos = new FakeCustomerPhotos;

    $this->build = fn (?FakeBusinessContext $business = null): AttachCustomerPhoto => new AttachCustomerPhoto(
        $this->customers,
        $this->photos,
        new CustomerPresenter($this->phones, $this->addresses, $this->photos),
        $business ?? new FakeBusinessContext,
    );

    $this->useCase = ($this->build)();

    $this->attach = fn (
        string $customerId = CustomerFixtures::CUSTOMER_ID,
        string $sourcePath = CustomerFixtures::PHOTO_SOURCE_PATH,
        string $fileName = CustomerFixtures::PHOTO_FILE_NAME,
        string $mimeType = CustomerFixtures::PHOTO_MIME_TYPE,
        int $sizeInBytes = CustomerFixtures::PHOTO_SIZE_IN_BYTES,
    ) => $this->useCase->handle(
        new AttachCustomerPhotoInput($customerId, $sourcePath, $fileName, $mimeType, $sizeInBytes),
    );
});

describe('attaching a photo', function () {
    beforeEach(function () {
        $this->customers->store(CustomerFixtures::customer());
        $this->photos->knows(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID);
    });

    it('hands the upload to the port exactly as it arrived', function () {
        ($this->attach)();

        expect($this->photos->replacements)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'customerId' => CustomerFixtures::CUSTOMER_ID,
            'sourcePath' => CustomerFixtures::PHOTO_SOURCE_PATH,
            'fileName' => CustomerFixtures::PHOTO_FILE_NAME,
        ]]);
    });

    it('files the photo under the business in context, never under one the port has to guess', function () {
        ($this->attach)();

        expect($this->photos->replacements[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('answers with the whole customer, the new photo included', function () {
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());
        $this->addresses->store(CustomerFixtures::CUSTOMER_ID, CustomerFixtures::address());

        $data = ($this->attach)()->value();

        expect($data)->toBeInstanceOf(CustomerData::class)
            ->and($data->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($data->name)->toBe(CustomerFixtures::NAME)
            ->and($data->email)->toBe(CustomerFixtures::EMAIL)
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($data->birthDate)->toEqual(CustomerFixtures::birthDate())
            ->and($data->notes)->toBe(CustomerFixtures::NOTES)
            ->and($data->address?->street)->toBe(CustomerFixtures::STREET)
            ->and($data->photoUrl)->toBe(
                FakeCustomerPhotos::urlOf(CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_FILE_NAME),
            )
            ->and($data->createdAt)->toEqual(CustomerFixtures::now());
    });

    it('replaces the photo a customer already had, keeping only the last one', function () {
        ($this->attach)();

        $data = ($this->attach)(fileName: 'grace.webp', mimeType: 'image/webp')->value();

        expect($this->photos->replacements)->toHaveCount(2)
            ->and($data->photoUrl)->toEndWith('/grace.webp');
    });

    it('names the customer by the uuid, never by a row number', function () {
        expect(($this->attach)()->value()->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and(($this->attach)()->value()->id)->not->toBe('1');
    });

    it('looks the customer up under the business in context, never one a caller could name', function () {
        ($this->attach)();

        expect($this->customers->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});

describe('refusing an upload', function () {
    it('does not touch the photo of a customer that belongs to another business', function () {
        $this->customers->store(CustomerFixtures::customer(businessId: CustomerFixtures::OTHER_BUSINESS_ID));

        $response = ($this->attach)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->photos->replacements)->toBe([]);
    });

    it('answers not found, never forbidden, to a caller reaching into another business', function () {
        $this->customers->store(CustomerFixtures::customer());

        $response = ($this->build)(new FakeBusinessContext(CustomerFixtures::OTHER_BUSINESS_ID))
            ->handle(new AttachCustomerPhotoInput(
                CustomerFixtures::CUSTOMER_ID,
                CustomerFixtures::PHOTO_SOURCE_PATH,
                CustomerFixtures::PHOTO_FILE_NAME,
                CustomerFixtures::PHOTO_MIME_TYPE,
                CustomerFixtures::PHOTO_SIZE_IN_BYTES,
            ));

        expect($response->error()->code)->toBe('customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->kind)->not->toBe(DomainFailureKind::Forbidden)
            ->and($this->photos->replacements)->toBe([]);
    });

    it('answers not found for a customer nobody has, having stored nothing', function () {
        $response = ($this->attach)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('customer_not_found')
            ->and($this->photos->replacements)->toBe([]);
    });

    it('refuses an upload its own rules refuse, without asking the repository or the port', function (array $overrides, string $code, DomainFailureKind $kind) {
        $this->customers->store(CustomerFixtures::customer());

        $response = ($this->attach)(...$overrides);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe($kind)
            ->and($this->customers->businessIdsSeen)->toBe([])
            ->and($this->photos->replacements)->toBe([]);
    })->with([
        'an identifier that cannot be a customer' => [
            ['customerId' => 'not-a-uuid'], 'customer_not_found', DomainFailureKind::NotFound,
        ],
        'no file behind the upload' => [
            ['sourcePath' => '   '], 'unsupported_customer_photo', DomainFailureKind::Invalid,
        ],
        'a type it does not serve' => [
            ['mimeType' => 'image/gif'], 'unsupported_customer_photo', DomainFailureKind::Invalid,
        ],
        'an empty file' => [
            ['sizeInBytes' => 0], 'unsupported_customer_photo', DomainFailureKind::Invalid,
        ],
        'a file larger than it stores' => [
            ['sizeInBytes' => AttachCustomerPhotoInput::MAXIMUM_BYTES + 1], 'customer_photo_too_large', DomainFailureKind::Invalid,
        ],
    ]);

    it('takes a file exactly as large as it stores', function () {
        $this->customers->store(CustomerFixtures::customer());
        $this->photos->knows(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID);

        $response = ($this->attach)(sizeInBytes: AttachCustomerPhotoInput::MAXIMUM_BYTES);

        expect($response->succeeded())->toBeTrue()
            ->and($this->photos->replacements)->toHaveCount(1);
    });
});
