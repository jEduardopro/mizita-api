<?php

declare(strict_types=1);

namespace App\Domains\Links\ValueObjects;

use App\Domains\Links\Exceptions\InvalidLinkUrl;
use App\Domains\Links\Exceptions\LinkPlatformMismatch;

final readonly class LinkUrl
{
    public const MAXIMUM_LENGTH = 2048;

    /**
     * @var list<string>
     */
    private const ACCEPTED_SCHEMES = ['http', 'https'];

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidLinkUrl
     * @throws LinkPlatformMismatch
     */
    public static function forPlatform(LinkPlatform $platform, string $url): self
    {
        $candidate = trim($url);

        if ($candidate === '') {
            throw InvalidLinkUrl::empty();
        }

        if (mb_strlen($candidate) > self::MAXIMUM_LENGTH) {
            throw InvalidLinkUrl::tooLong();
        }

        $host = self::hostOf($candidate);

        if (! $platform->accepts($host)) {
            throw LinkPlatformMismatch::between($platform->value, $host);
        }

        return new self($candidate);
    }

    public static function restore(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * @throws InvalidLinkUrl
     */
    private static function hostOf(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            throw InvalidLinkUrl::malformed();
        }

        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, self::ACCEPTED_SCHEMES, true)) {
            throw InvalidLinkUrl::unsupportedScheme();
        }

        $host = mb_strtolower((string) ($parts['host'] ?? ''));

        if ($host === '' || ! str_contains($host, '.')) {
            throw InvalidLinkUrl::malformed();
        }

        return self::withoutLeadingWww($host);
    }

    private static function withoutLeadingWww(string $host): string
    {
        return str_starts_with($host, 'www.') ? mb_substr($host, 4) : $host;
    }
}
