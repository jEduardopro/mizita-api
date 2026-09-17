<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Customers\CustomerFixtures;
use Tests\Support\Customers\FakeCustomerPhotos;
use Tests\Support\FakeBusinessContext;

function customerPhotoRefusalFrom(callable $call): ?CustomerNotFound
{
    try {
        $call();
    } catch (CustomerNotFound $failure) {
        return $failure;
    }

    return null;
}

beforeEach(function () {
    $this->photos = new FakeCustomerPhotos;

    $this->ours = FakeBusinessContext::BUSINESS_ID;
    $this->theirs = CustomerFixtures::OTHER_BUSINESS_ID;
});

describe('reading the photo of one customer', function () {
    it('hands back the photo filed under the business it was asked about', function () {
        $this->photos->store($this->ours, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        expect($this->photos->urlFor($this->ours, CustomerFixtures::CUSTOMER_ID))->toBe(CustomerFixtures::PHOTO_URL);
    });

    it('hands back nothing for a customer of another business', function () {
        $this->photos->store($this->theirs, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        expect($this->photos->urlFor($this->ours, CustomerFixtures::CUSTOMER_ID))->toBeNull();
    });

    it('answers the wrong business exactly as it answers a customer nobody has', function () {
        $this->photos->store($this->theirs, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        expect($this->photos->urlFor($this->ours, CustomerFixtures::CUSTOMER_ID))
            ->toBe($this->photos->urlFor($this->ours, CustomerFixtures::FOREIGN_CUSTOMER_ID));
    });
});

describe('reading the photos of a page', function () {
    beforeEach(function () {
        $this->photos->store($this->ours, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);
        $this->photos->store($this->theirs, CustomerFixtures::SECOND_CUSTOMER_ID, CustomerFixtures::PHOTO_URL);
    });

    it('leaves the customers of another business out of the map entirely', function () {
        $urls = $this->photos->urlsFor($this->ours, [
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::SECOND_CUSTOMER_ID,
        ]);

        expect($urls)->toHaveKey(CustomerFixtures::CUSTOMER_ID)
            ->and(array_key_exists(CustomerFixtures::SECOND_CUSTOMER_ID, $urls))->toBeFalse()
            ->and($urls)->toHaveCount(1);
    });

    it('leaves a customer of this business without a photo out of the map too', function () {
        $urls = $this->photos->urlsFor($this->ours, [
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::THIRD_CUSTOMER_ID,
        ]);

        expect(array_key_exists(CustomerFixtures::THIRD_CUSTOMER_ID, $urls))->toBeFalse();
    });

    it('answers a whole page in one call, however many businesses the ids come from', function () {
        $this->photos->urlsFor($this->ours, [
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::SECOND_CUSTOMER_ID,
            CustomerFixtures::THIRD_CUSTOMER_ID,
        ]);

        expect($this->photos->batchReads)->toHaveCount(1)
            ->and($this->photos->reads)->toBe([]);
    });

    it('answers an empty page with an empty map', function () {
        expect($this->photos->urlsFor($this->ours, []))->toBe([]);
    });
});

describe('replacing a photo', function () {
    it('files the photo under the business it was given', function () {
        $this->photos->knows($this->ours, CustomerFixtures::CUSTOMER_ID);

        $this->photos->replace(
            $this->ours,
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::PHOTO_SOURCE_PATH,
            CustomerFixtures::PHOTO_FILE_NAME,
        );

        expect($this->photos->urlFor($this->ours, CustomerFixtures::CUSTOMER_ID))
            ->toBe(FakeCustomerPhotos::urlOf(CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_FILE_NAME))
            ->and($this->photos->urlFor($this->theirs, CustomerFixtures::CUSTOMER_ID))->toBeNull();
    });

    it('refuses a customer that belongs to another business', function () {
        $this->photos->knows($this->theirs, CustomerFixtures::CUSTOMER_ID);

        expect(fn () => $this->photos->replace(
            $this->ours,
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::PHOTO_SOURCE_PATH,
            CustomerFixtures::PHOTO_FILE_NAME,
        ))->toThrow(CustomerNotFound::class);
    });

    it('refuses the wrong business with the same failure it gives a customer nobody has', function (string $customerId) {
        $this->photos->knows($this->theirs, CustomerFixtures::CUSTOMER_ID);

        $failure = customerPhotoRefusalFrom(fn () => $this->photos->replace(
            $this->ours,
            $customerId,
            CustomerFixtures::PHOTO_SOURCE_PATH,
            CustomerFixtures::PHOTO_FILE_NAME,
        ));

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure?->errorCode())->toBe('customer_not_found')
            ->and($failure?->kind())->toBe(DomainFailureKind::NotFound)
            ->and($failure?->kind())->not->toBe(DomainFailureKind::Forbidden);
    })->with([
        'a customer of another business' => CustomerFixtures::CUSTOMER_ID,
        'a customer nobody has' => CustomerFixtures::FOREIGN_CUSTOMER_ID,
    ]);

    it('files nothing when it refuses', function () {
        $this->photos->knows($this->theirs, CustomerFixtures::CUSTOMER_ID);

        customerPhotoRefusalFrom(fn () => $this->photos->replace(
            $this->ours,
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::PHOTO_SOURCE_PATH,
            CustomerFixtures::PHOTO_FILE_NAME,
        ));

        expect($this->photos->replacements)->toBe([])
            ->and($this->photos->urlFor($this->theirs, CustomerFixtures::CUSTOMER_ID))->toBeNull();
    });
});

describe('removing a photo', function () {
    it('clears the photo of the business it was given', function () {
        $this->photos->store($this->ours, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        $this->photos->remove($this->ours, CustomerFixtures::CUSTOMER_ID);

        expect($this->photos->urlFor($this->ours, CustomerFixtures::CUSTOMER_ID))->toBeNull();
    });

    it('refuses a customer that belongs to another business, and leaves their photo where it was', function () {
        $this->photos->store($this->theirs, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        expect(fn () => $this->photos->remove($this->ours, CustomerFixtures::CUSTOMER_ID))
            ->toThrow(CustomerNotFound::class)
            ->and($this->photos->urlFor($this->theirs, CustomerFixtures::CUSTOMER_ID))->toBe(CustomerFixtures::PHOTO_URL)
            ->and($this->photos->removals)->toBe([]);
    });

    it('refuses a customer nobody has with not found, never forbidden', function () {
        $failure = customerPhotoRefusalFrom(fn () => $this->photos->remove($this->ours, CustomerFixtures::FOREIGN_CUSTOMER_ID));

        expect($failure)->toBeInstanceOf(CustomerNotFound::class)
            ->and($failure?->errorCode())->toBe('customer_not_found')
            ->and($failure?->kind())->toBe(DomainFailureKind::NotFound);
    });
});
