<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\VerifyBusinessPageInput;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\BusinessPageSlug;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

describe('a slug shaped like one a business could own', function () {
    it('validates the slug a booking page is published under', function (string $slug) {
        expect(fn () => (new VerifyBusinessPageInput($slug))->validate())->not->toThrow(Throwable::class);
    })->with([
        'the fixture slug' => PublicCatalogFixtures::SLUG,
        'one word' => 'ada',
        'digits only' => '2026',
        'the longest length a business may choose' => str_repeat('a', BusinessPageSlug::MAXIMUM_LENGTH),
    ]);
});

describe('a slug no business could own', function () {
    it('refuses it as a business that does not exist', function (string $slug) {
        expect(fn () => (new VerifyBusinessPageInput($slug))->validate())->toThrow(BusinessPageNotFound::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'uppercase' => 'Ada-Salon',
        'an accent' => 'peluquería',
        'a double hyphen' => 'ada--salon',
        'a path' => '../ada-salon',
        'a trailing newline' => "ada-salon\n",
        'a file name' => 'robots.txt',
        'one past the maximum length' => str_repeat('a', BusinessPageSlug::MAXIMUM_LENGTH + 1),
    ]);

    it('refuses it with a not found domain failure the use case can return', function () {
        expect(fn () => (new VerifyBusinessPageInput('Ada-Salon'))->validate())
            ->toThrow(function (BusinessPageNotFound $refusal) {
                expect($refusal)->toBeInstanceOf(DomainFailure::class)
                    ->and($refusal->errorCode())->toBe('business_not_found')
                    ->and($refusal->kind())->toBe(DomainFailureKind::NotFound);
            });
    });
});
