<?php

declare(strict_types=1);

namespace App\Http\PublicLinks;

final class PublicLinks
{
    private const CONFIG_FILE = 'public-links';

    private const FACEBOOK = 'facebook';

    private const INSTAGRAM = 'instagram';

    private const CONTACT = 'contact';

    private const WEB_SCHEMES = ['https', 'http'];

    private const MAIL_SCHEME = 'mailto';

    private const WHITESPACE_OR_CONTROL_CHARACTER = '/[\s\x00-\x1F\x7F]/u';

    /**
     * @return array{facebook: ?string, instagram: ?string, contact: ?string}
     */
    public function describe(): array
    {
        return [
            self::FACEBOOK => $this->webLink(self::FACEBOOK),
            self::INSTAGRAM => $this->webLink(self::INSTAGRAM),
            self::CONTACT => $this->contactLink(),
        ];
    }

    private function webLink(string $name): ?string
    {
        $link = $this->configured($name);

        if ($link === null || ! $this->isWebUrl($link)) {
            return null;
        }

        return $link;
    }

    private function contactLink(): ?string
    {
        $link = $this->configured(self::CONTACT);

        if ($link === null) {
            return null;
        }

        if ($this->isWebUrl($link) || $this->isMailAddress($link)) {
            return $link;
        }

        return null;
    }

    private function configured(string $name): ?string
    {
        $configured = config(self::CONFIG_FILE.'.'.$name);

        if (! is_string($configured)) {
            return null;
        }

        $link = trim($configured);

        if ($link === '' || preg_match(self::WHITESPACE_OR_CONTROL_CHARACTER, $link) !== 0) {
            return null;
        }

        return $link;
    }

    private function isWebUrl(string $link): bool
    {
        $parts = parse_url($link);

        if (! is_array($parts) || ! in_array($this->schemeOf($parts), self::WEB_SCHEMES, true)) {
            return false;
        }

        return ($parts['host'] ?? '') !== '';
    }

    private function isMailAddress(string $link): bool
    {
        $parts = parse_url($link);

        if (! is_array($parts) || $this->schemeOf($parts) !== self::MAIL_SCHEME) {
            return false;
        }

        return ($parts['path'] ?? '') !== '';
    }

    /**
     * @param  array<string, int|string>  $parts
     */
    private function schemeOf(array $parts): ?string
    {
        if (! is_string($parts['scheme'] ?? null)) {
            return null;
        }

        return strtolower($parts['scheme']);
    }
}
