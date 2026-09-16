<?php

declare(strict_types=1);

namespace App\Domains\Links\ValueObjects;

use App\Domains\Links\Exceptions\InvalidLinkPlatform;

enum LinkPlatform: string
{
    case Website = 'website';

    case Instagram = 'instagram';

    case Facebook = 'facebook';

    case TikTok = 'tiktok';

    case X = 'x';

    case LinkedIn = 'linkedin';

    case YouTube = 'youtube';

    case WhatsApp = 'whatsapp';

    /**
     * @throws InvalidLinkPlatform
     */
    public static function fromValue(string $value): self
    {
        $platform = self::tryFrom(mb_strtolower(trim($value)));

        if ($platform === null) {
            throw InvalidLinkPlatform::withValue($value);
        }

        return $platform;
    }

    public function acceptsAnyHost(): bool
    {
        return $this === self::Website;
    }

    /**
     * @return list<string>
     */
    public function hostSuffixes(): array
    {
        return match ($this) {
            self::Website => [],
            self::Instagram => ['instagram.com'],
            self::Facebook => ['facebook.com', 'fb.com', 'fb.me'],
            self::TikTok => ['tiktok.com'],
            self::X => ['x.com', 'twitter.com'],
            self::LinkedIn => ['linkedin.com'],
            self::YouTube => ['youtube.com', 'youtu.be'],
            self::WhatsApp => ['whatsapp.com', 'wa.me'],
        };
    }

    public function accepts(string $host): bool
    {
        if ($this->acceptsAnyHost()) {
            return true;
        }

        foreach ($this->hostSuffixes() as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }
}
