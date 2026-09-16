<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\BusinessLinkList;
use App\Domains\Businesses\ValueObjects\BusinessLinkSnapshot;
use Throwable;

final class FakeBusinessLinkList implements BusinessLinkList
{
    /**
     * @var array<string, list<BusinessLinkSnapshot>>
     */
    private array $links = [];

    private ?Throwable $replaceFailure = null;

    /**
     * @var list<array{businessId: string, links: list<BusinessLinkSnapshot>}>
     */
    public array $replacements = [];

    /**
     * @var list<string>
     */
    public array $reads = [];

    public function store(string $businessId, BusinessLinkSnapshot ...$links): self
    {
        $this->links[$businessId] = array_values($links);

        return $this;
    }

    public function failingOnReplace(Throwable $failure): self
    {
        $this->replaceFailure = $failure;

        return $this;
    }

    /**
     * @return list<BusinessLinkSnapshot>
     */
    public function forBusiness(string $businessId): array
    {
        $this->reads[] = $businessId;

        return $this->links[$businessId] ?? [];
    }

    /**
     * @param  list<BusinessLinkSnapshot>  $links
     */
    public function replaceForBusiness(string $businessId, array $links): void
    {
        if ($this->replaceFailure !== null) {
            throw $this->replaceFailure;
        }

        $this->links[$businessId] = $links;
        $this->replacements[] = ['businessId' => $businessId, 'links' => $links];
    }
}
