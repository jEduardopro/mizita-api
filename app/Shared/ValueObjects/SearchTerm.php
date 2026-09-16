<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

final readonly class SearchTerm
{
    public const MAXIMUM_TOKENS = 10;

    private const WHITESPACE_RUN = '/\s+/u';

    private const TOKEN_SEPARATOR = ' ';

    /**
     * @param  list<string>  $tokens
     */
    private function __construct(
        private array $tokens,
        private string $raw,
    ) {}

    /**
     * @param  list<string>  $ignoredWords
     */
    public static function of(?string $raw, array $ignoredWords = []): ?self
    {
        $collapsed = self::collapse($raw ?? '');

        if ($collapsed === '') {
            return null;
        }

        $tokens = self::tokenize($collapsed, $ignoredWords);

        if ($tokens === []) {
            return null;
        }

        return new self($tokens, $collapsed);
    }

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    public function raw(): string
    {
        return $this->raw;
    }

    private static function collapse(string $raw): string
    {
        return trim((string) preg_replace(self::WHITESPACE_RUN, self::TOKEN_SEPARATOR, $raw));
    }

    /**
     * @param  list<string>  $ignoredWords
     * @return list<string>
     */
    private static function tokenize(string $collapsed, array $ignoredWords): array
    {
        $ignored = array_map(AccentFolding::fold(...), $ignoredWords);
        $tokens = [];

        foreach (explode(self::TOKEN_SEPARATOR, $collapsed) as $word) {
            $folded = AccentFolding::fold($word);

            if (in_array($folded, $ignored, true)) {
                continue;
            }

            if (in_array($folded, $tokens, true)) {
                continue;
            }

            $tokens[] = $folded;
        }

        return array_slice($tokens, 0, self::MAXIMUM_TOKENS);
    }
}
