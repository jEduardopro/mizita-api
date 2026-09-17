<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CustomerAddressData;
use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;
use Tests\Support\Customers\CustomerFixtures;
use Tests\Support\Customers\FakeCustomerAddressBook;
use Tests\Support\Customers\FakeCustomerPhoneBook;
use Tests\Support\Customers\FakeCustomerPhotos;
use Tests\Support\FakeBusinessContext;
use Tests\Support\PhoneNumbers;

beforeEach(function () {
    $this->phones = new FakeCustomerPhoneBook;
    $this->addresses = new FakeCustomerAddressBook;
    $this->photos = new FakeCustomerPhotos;

    $this->presenter = new CustomerPresenter($this->phones, $this->addresses, $this->photos);

    $this->page = fn (array $customers, int $total = 3, ?Pagination $pagination = null): Paginated => Paginated::of(
        $customers,
        $total,
        $pagination ?? Pagination::of(1, 20),
    );

    $this->describePage = fn (Paginated $page, string $businessId = FakeBusinessContext::BUSINESS_ID): Paginated => $this->presenter
        ->describePage($businessId, $page);
});

describe('describing one customer', function () {
    it('fills every field of the data the client reads', function () {
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());
        $this->addresses->store(CustomerFixtures::CUSTOMER_ID, CustomerFixtures::address());
        $this->photos->store(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        $data = $this->presenter->describe(CustomerFixtures::customer());

        expect($data)->toBeInstanceOf(CustomerData::class)
            ->and($data->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($data->name)->toBe(CustomerFixtures::NAME)
            ->and($data->email)->toBe(CustomerFixtures::EMAIL)
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($data->birthDate)->toEqual(CustomerFixtures::birthDate())
            ->and($data->notes)->toBe(CustomerFixtures::NOTES)
            ->and($data->address)->toBeInstanceOf(CustomerAddressData::class)
            ->and($data->address?->street)->toBe(CustomerFixtures::STREET)
            ->and($data->address?->city)->toBe(CustomerFixtures::CITY)
            ->and($data->address?->stateId)->toBe(CustomerFixtures::STATE_ID)
            ->and($data->address?->postalCode)->toBe(CustomerFixtures::POSTAL_CODE)
            ->and($data->address?->countryCode)->toBe(CustomerFixtures::COUNTRY_CODE)
            ->and($data->photoUrl)->toBe(CustomerFixtures::PHOTO_URL)
            ->and($data->createdAt)->toEqual(CustomerFixtures::now());
    });

    it('asks every book about the customer it was given', function () {
        $this->presenter->describe(CustomerFixtures::customer());

        expect($this->phones->reads)->toBe([CustomerFixtures::CUSTOMER_ID])
            ->and($this->addresses->reads)->toBe([CustomerFixtures::CUSTOMER_ID])
            ->and($this->photos->reads)->toBe([[
                'businessId' => FakeBusinessContext::BUSINESS_ID,
                'customerId' => CustomerFixtures::CUSTOMER_ID,
            ]]);
    });

    it('asks for the photo under the business the customer itself carries', function () {
        $this->presenter->describe(CustomerFixtures::customer(businessId: CustomerFixtures::OTHER_BUSINESS_ID));

        expect($this->photos->reads)->toBe([[
            'businessId' => CustomerFixtures::OTHER_BUSINESS_ID,
            'customerId' => CustomerFixtures::CUSTOMER_ID,
        ]]);
    });

    it('carries no photo when the one on file hangs off another business', function () {
        $this->photos->store(CustomerFixtures::OTHER_BUSINESS_ID, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        $data = $this->presenter->describe(CustomerFixtures::customer());

        expect($data->photoUrl)->toBeNull();
    });

    it('carries nothing where a customer has nothing', function () {
        $data = $this->presenter->describe(CustomerFixtures::customer(
            email: null,
            birthDate: null,
            notes: null,
        ));

        expect($data->email)->toBeNull()
            ->and($data->phone)->toBeNull()
            ->and($data->birthDate)->toBeNull()
            ->and($data->notes)->toBeNull()
            ->and($data->address)->toBeNull()
            ->and($data->photoUrl)->toBeNull();
    });

    it('keeps the empty parts of an address that only carries a street', function () {
        $this->addresses->store(CustomerFixtures::CUSTOMER_ID, CustomerFixtures::address(
            city: null,
            stateId: null,
            postalCode: null,
        ));

        $address = $this->presenter->describe(CustomerFixtures::customer())->address;

        expect($address?->street)->toBe(CustomerFixtures::STREET)
            ->and($address?->city)->toBeNull()
            ->and($address?->stateId)->toBeNull()
            ->and($address?->postalCode)->toBeNull();
    });

    it('does not carry the business it belongs to into the data', function () {
        $data = $this->presenter->describe(CustomerFixtures::customer());

        expect(get_object_vars($data))->not->toHaveKey('businessId');
    });
});

describe('describing a page of customers', function () {
    beforeEach(function () {
        $this->customers = [
            CustomerFixtures::customer(),
            CustomerFixtures::customer(id: CustomerFixtures::SECOND_CUSTOMER_ID, name: 'Grace Hopper'),
            CustomerFixtures::customer(id: CustomerFixtures::THIRD_CUSTOMER_ID, name: 'Katherine Johnson'),
        ];
    });

    it('asks for the phones of the whole page in one call, never one per row', function () {
        ($this->describePage)(($this->page)($this->customers));

        expect($this->phones->batchReads)->toHaveCount(1)
            ->and($this->phones->batchReads[0])->toBe([
                CustomerFixtures::CUSTOMER_ID,
                CustomerFixtures::SECOND_CUSTOMER_ID,
                CustomerFixtures::THIRD_CUSTOMER_ID,
            ])
            ->and($this->phones->reads)->toBe([]);
    });

    it('gives each row the phone that belongs to it, and none to the rest', function () {
        $this->phones->store(CustomerFixtures::SECOND_CUSTOMER_ID, PhoneNumbers::mexican());

        $page = ($this->describePage)(($this->page)($this->customers));

        expect(array_map(static fn (CustomerData $data): ?string => $data->phone?->e164(), $page->items))
            ->toBe([null, PhoneNumbers::MX_E164, null]);
    });

    it('leaves the address out of every row of a list, and reads no address at all', function () {
        $this->addresses->store(CustomerFixtures::CUSTOMER_ID, CustomerFixtures::address());

        $page = ($this->describePage)(($this->page)($this->customers));

        expect(array_map(static fn (CustomerData $data): mixed => $data->address, $page->items))
            ->toBe([null, null, null])
            ->and($this->addresses->reads)->toBe([]);
    });

    it('asks for the photos of the whole page in one call, never one per row', function () {
        ($this->describePage)(($this->page)($this->customers));

        expect($this->photos->batchReads)->toHaveCount(1)
            ->and($this->photos->batchReads[0]['customerIds'])->toBe([
                CustomerFixtures::CUSTOMER_ID,
                CustomerFixtures::SECOND_CUSTOMER_ID,
                CustomerFixtures::THIRD_CUSTOMER_ID,
            ])
            ->and($this->photos->reads)->toBe([]);
    });

    it('asks for the photos of the page under the business it was handed', function () {
        ($this->describePage)(($this->page)($this->customers), CustomerFixtures::OTHER_BUSINESS_ID);

        expect($this->photos->batchReads)->toHaveCount(1)
            ->and($this->photos->batchReads[0]['businessId'])->toBe(CustomerFixtures::OTHER_BUSINESS_ID);
    });

    it('still asks once for a whole page it has to scope to a business', function () {
        ($this->describePage)(($this->page)($this->customers), CustomerFixtures::OTHER_BUSINESS_ID);

        expect($this->photos->batchReads)->toHaveCount(1)
            ->and($this->photos->reads)->toBe([]);
    });

    it('gives each row the photo that belongs to it, and none to the rest', function () {
        $this->photos->store(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::SECOND_CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        $page = ($this->describePage)(($this->page)($this->customers));

        expect(array_map(static fn (CustomerData $data): ?string => $data->photoUrl, $page->items))
            ->toBe([null, CustomerFixtures::PHOTO_URL, null]);
    });

    it('gives no row a photo filed under another business', function () {
        $this->photos->store(CustomerFixtures::OTHER_BUSINESS_ID, CustomerFixtures::SECOND_CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        $page = ($this->describePage)(($this->page)($this->customers));

        expect(array_map(static fn (CustomerData $data): ?string => $data->photoUrl, $page->items))
            ->toBe([null, null, null]);
    });

    it('drops the address from a row while keeping its photo, which is the asymmetry a list lives with', function () {
        $this->addresses->store(CustomerFixtures::CUSTOMER_ID, CustomerFixtures::address());
        $this->photos->store(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        $data = ($this->describePage)(($this->page)([CustomerFixtures::customer()], 1))->items[0];

        expect($data->address)->toBeNull()
            ->and($data->photoUrl)->toBe(CustomerFixtures::PHOTO_URL);
    });

    it('describes every other field of a row exactly as the detail does', function () {
        $data = ($this->describePage)(($this->page)([CustomerFixtures::customer()], 1))->items[0];

        expect($data)->toBeInstanceOf(CustomerData::class)
            ->and($data->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($data->name)->toBe(CustomerFixtures::NAME)
            ->and($data->email)->toBe(CustomerFixtures::EMAIL)
            ->and($data->birthDate)->toEqual(CustomerFixtures::birthDate())
            ->and($data->notes)->toBe(CustomerFixtures::NOTES)
            ->and($data->createdAt)->toEqual(CustomerFixtures::now());
    });

    it('keeps the total, the page and the page size it was handed', function () {
        $page = ($this->describePage)(($this->page)($this->customers, 42, Pagination::of(3, 25)));

        expect($page->total)->toBe(42)
            ->and($page->pagination->page)->toBe(3)
            ->and($page->pagination->perPage)->toBe(25)
            ->and($page->lastPage())->toBe(2)
            ->and($page->items)->toHaveCount(3);
    });

    it('describes an empty page without asking for anything it does not need', function () {
        $page = ($this->describePage)(($this->page)([], 0));

        expect($page->items)->toBe([])
            ->and($page->total)->toBe(0)
            ->and($this->phones->batchReads)->toBe([[]])
            ->and($this->photos->batchReads)->toBe([[
                'businessId' => FakeBusinessContext::BUSINESS_ID,
                'customerIds' => [],
            ]])
            ->and($this->addresses->reads)->toBe([]);
    });
});
