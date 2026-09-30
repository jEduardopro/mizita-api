<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\DescribeBusinessPageSharePreviewInput;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\BusinessPageSlug;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

describe('a slug shaped like one a business could own', function () {
    it('validates the slug a booking page is published under', function () {
        $input = new DescribeBusinessPageSharePreviewInput(PublicCatalogFixtures::SLUG);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('validates a slug at the longest length a business may choose', function () {
        $input = new DescribeBusinessPageSharePreviewInput(str_repeat('a', BusinessPageSlug::MAXIMUM_LENGTH));

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('validates digits and single hyphens between words', function (string $slug) {
        expect(fn () => (new DescribeBusinessPageSharePreviewInput($slug))->validate())->not->toThrow(Throwable::class);
    })->with([
        'one word' => 'ada',
        'digits only' => '2026',
        'words and digits' => 'salon-24-horas',
    ]);
});

describe('a slug no business could own', function () {
    it('refuses it as a business that does not exist', function (string $slug) {
        expect(fn () => (new DescribeBusinessPageSharePreviewInput($slug))->validate())
            ->toThrow(BusinessPageNotFound::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a space inside' => 'ada salon',
        'uppercase' => 'Ada-Salon',
        'an accent' => 'peluquería',
        'unicode' => 'peluquería-ñandú',
        'an underscore' => 'ada_salon',
        'a leading hyphen' => '-ada',
        'a trailing hyphen' => 'ada-',
        'a double hyphen' => 'ada--salon',
        'a path' => '../ada-salon',
        'a trailing newline' => "ada-salon\n",
        'one past the maximum length' => str_repeat('a', BusinessPageSlug::MAXIMUM_LENGTH + 1),
    ]);

    it('refuses it with a domain failure the use case can return', function () {
        expect(fn () => (new DescribeBusinessPageSharePreviewInput('Ada-Salon'))->validate())
            ->toThrow(function (BusinessPageNotFound $refusal) {
                expect($refusal)->toBeInstanceOf(DomainFailure::class)
                    ->and($refusal->errorCode())->toBe('business_not_found');
            });
    });
});
