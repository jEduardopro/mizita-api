<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Shared\ValueObjects\Paginated;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class PaginatedCollection extends ResourceCollection
{
    /**
     * @param  Paginated<mixed>  $page
     * @param  class-string<JsonResource>  $resource
     */
    private function __construct(private readonly Paginated $page, string $resource)
    {
        $this->collects = $resource;

        parent::__construct($page->items);
    }

    /**
     * @param  Paginated<mixed>  $page
     * @param  class-string<JsonResource>  $resource
     */
    public static function of(Paginated $page, string $resource): self
    {
        return new self($page, $resource);
    }

    /**
     * @return array{meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'current_page' => $this->page->pagination->page,
                'per_page' => $this->page->pagination->perPage,
                'total' => $this->page->total,
                'last_page' => $this->page->lastPage(),
            ],
        ];
    }
}
