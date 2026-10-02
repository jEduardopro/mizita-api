<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use App\Domains\Platform\Contracts\PlatformBusinessDirectory;
use App\Domains\Platform\ValueObjects\PlatformBusinessQuery;
use App\Domains\Platform\ValueObjects\PlatformBusinessRecord;
use App\Shared\ValueObjects\Paginated;
use Throwable;

final class FakePlatformBusinessDirectory implements PlatformBusinessDirectory
{
    /**
     * @var Paginated<PlatformBusinessRecord>|null
     */
    private ?Paginated $page = null;

    private ?Throwable $failure = null;

    /**
     * @var list<PlatformBusinessQuery>
     */
    public array $queries = [];

    /**
     * @param  Paginated<PlatformBusinessRecord>  $page
     */
    public function returning(Paginated $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function failingWith(Throwable $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    /**
     * @return Paginated<PlatformBusinessRecord>
     */
    public function page(PlatformBusinessQuery $query): Paginated
    {
        $this->queries[] = $query;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->page ?? Paginated::of([], 0, $query->pagination);
    }
}
