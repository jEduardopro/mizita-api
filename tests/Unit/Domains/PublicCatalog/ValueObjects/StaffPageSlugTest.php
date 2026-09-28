<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Exceptions\StaffBookingPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\StaffPageSlug;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

describe('a staff slug shaped like a link the business shared', function () {
    it('keeps the slug it was given', function (string $slug) {
        expect(StaffPageSlug::fromString($slug)->value)->toBe($slug);
    })->with([
        'a single word' => 'ada',
        'hyphenated words' => 'jose-pablo',
        'a suffix added on a clash' => 'jose-pablo-2',
        'digits alone' => '2026',
        'a single character' => 'a',
    ]);

    it('accepts a slug of exactly the maximum length', function () {
        $slug = str_repeat('a', StaffPageSlug::MAXIMUM_LENGTH);

        expect(StaffPageSlug::fromString($slug)->value)->toBe($slug)
            ->and(StaffPageSlug::MAXIMUM_LENGTH)->toBe(60);
    });
});

describe('a staff slug no link could carry', function () {
    it('refuses it as a page that does not exist', function (string $slug) {
        expect(fn () => StaffPageSlug::fromString($slug))->toThrow(StaffBookingPageNotFound::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'uppercase' => 'Jose-Pablo',
        'an underscore' => 'jose_pablo',
        'a leading hyphen' => '-jose',
        'a trailing hyphen' => 'jose-',
        'a doubled hyphen' => 'jose--pablo',
        'an accent' => 'josé',
        'an inner space' => 'jose pablo',
        'a trailing newline' => "jose\n",
        'a path separator' => 'jose/pablo',
        'a traversal' => '../jose',
        'one past the maximum length' => str_repeat('a', StaffPageSlug::MAXIMUM_LENGTH + 1),
    ]);

    it('refuses with a not found failure, so an unknown link reads as a 404 and never as a 403', function () {
        try {
            StaffPageSlug::fromString('Not A Slug');
            $refusal = null;
        } catch (StaffBookingPageNotFound $thrown) {
            $refusal = $thrown;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('staff_member_not_found')
            ->and($refusal->kind())->toBe(DomainFailureKind::NotFound)
            ->and($refusal->kind())->not->toBe(DomainFailureKind::Forbidden);
    });
});
