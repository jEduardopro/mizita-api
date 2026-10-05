<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\PublicCatalog\Infrastructure\Gateways\BusinessesSitemapBusinesses;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->connection = (new BusinessModel)->getConnection();

    $this->pretendedQuery = function (): array {
        $pages = null;

        $queries = $this->connection->pretend(function () use (&$pages): void {
            $pages = (new BusinessesSitemapBusinesses)->publishedPages();
        });

        return ['pages' => $pages, 'queries' => $queries];
    };
});

describe('the one query the sitemap read issues', function () {
    it('reads the businesses table once and returns nothing when it holds nothing', function () {
        ['pages' => $pages, 'queries' => $queries] = ($this->pretendedQuery)();

        expect($pages)->toBe([])
            ->and($queries)->toHaveCount(1)
            ->and($queries[0]['query'])->toContain('from "businesses"');
    });

    it('selects the slug and the two timestamps and no other column', function () {
        $sql = ($this->pretendedQuery)()['queries'][0]['query'];

        expect($sql)->toStartWith('select "slug", "created_at", "updated_at" from "businesses"')
            ->and($sql)->not->toContain('*');
    });

    it('leaves a closed business out of the public sitemap', function () {
        expect(class_uses_recursive(BusinessModel::class))->toContain(SoftDeletes::class)
            ->and(($this->pretendedQuery)()['queries'][0]['query'])->toContain('"businesses"."deleted_at" is null');
    });

    it('filters on no tenant, since the sitemap deliberately spans every business', function () {
        expect(($this->pretendedQuery)()['queries'][0]['query'])->not->toContain('business_id');
    });

    it('caps the list under the sitemap protocol limit, in a stable order', function () {
        $sql = ($this->pretendedQuery)()['queries'][0]['query'];

        expect($sql)->toContain('order by "id" asc')
            ->and($sql)->toEndWith('limit 49000');
    });
});
