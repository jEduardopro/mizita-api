<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Search;

final readonly class SearchableColumns
{
    /**
     * @param  list<string>  $textColumns
     * @param  list<string>  $numericColumns
     */
    private function __construct(
        private array $textColumns,
        private array $numericColumns,
    ) {}

    public static function text(string ...$columns): self
    {
        return new self(array_values($columns), []);
    }

    public function alsoMatchingNumeric(string ...$columns): self
    {
        return new self($this->textColumns, [...$this->numericColumns, ...array_values($columns)]);
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return $this->textColumns;
    }

    /**
     * @return list<string>
     */
    public function numericColumns(): array
    {
        return $this->numericColumns;
    }
}
