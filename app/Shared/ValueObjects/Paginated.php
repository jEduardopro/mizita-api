<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

/**
 * @template TItem
 */
final readonly class Paginated
{
    private const FIRST_PAGE = 1;

    /**
     * @param  list<TItem>  $items
     */
    private function __construct(
        public array $items,
        public int $total,
        public Pagination $pagination,
    ) {}

    /**
     * @template TValue
     *
     * @param  list<TValue>  $items
     * @return self<TValue>
     */
    public static function of(array $items, int $total, Pagination $pagination): self
    {
        return new self($items, max(0, $total), $pagination);
    }

    public function lastPage(): int
    {
        return max(self::FIRST_PAGE, (int) ceil($this->total / $this->pagination->perPage));
    }

    /**
     * @template TMapped
     *
     * @param  callable(TItem): TMapped  $mapper
     * @return self<TMapped>
     */
    public function map(callable $mapper): self
    {
        return new self(
            array_values(array_map($mapper, $this->items)),
            $this->total,
            $this->pagination,
        );
    }
}
