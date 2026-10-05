<?php

declare(strict_types=1);

use App\Http\Seo\RobotsDirective;
use App\Http\Seo\SearchIndexing;
use Tests\TestCase;

uses(TestCase::class);

describe('a site open to search engines', function () {
    beforeEach(function () {
        config(['seo.indexable' => true]);
    });

    it('permits indexing', function () {
        expect((new SearchIndexing)->permitted())->toBeTrue();
    });

    it('lets an indexable page be indexed and followed', function () {
        expect((new SearchIndexing)->directiveForIndexablePage())->toBe(RobotsDirective::Index)
            ->and(RobotsDirective::Index->value)->toBe('index, follow');
    });
});

describe('a site closed to search engines', function () {
    it('refuses indexing for every value that is not a yes', function (mixed $indexable) {
        config(['seo.indexable' => $indexable]);

        expect((new SearchIndexing)->permitted())->toBeFalse();
    })->with([
        'false' => false,
        'null' => null,
        'zero' => 0,
        'an empty string' => '',
    ]);

    it('refuses indexing when the setting is missing altogether', function () {
        config(['seo' => []]);

        expect((new SearchIndexing)->permitted())->toBeFalse();
    });

    it('keeps even an indexable page out of the index', function () {
        config(['seo.indexable' => false]);

        expect((new SearchIndexing)->directiveForIndexablePage())->toBe(RobotsDirective::NoIndex)
            ->and(RobotsDirective::NoIndex->value)->toBe('noindex');
    });
});
