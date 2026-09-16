<?php

declare(strict_types=1);

use App\Domains\Links\Exceptions\InvalidLinkPlatform;
use App\Domains\Links\ValueObjects\LinkPlatform;
use App\Shared\Contracts\DomainFailure;

describe('the closed set of platforms', function () {
    it('names exactly the networks a business may link to', function () {
        expect(array_column(LinkPlatform::cases(), 'value'))->toBe([
            'website',
            'instagram',
            'facebook',
            'tiktok',
            'x',
            'linkedin',
            'youtube',
            'whatsapp',
        ]);
    });

    it('stores each platform as the lower case key the column holds', function (LinkPlatform $platform) {
        expect($platform->value)->toBe(mb_strtolower($platform->value));
    })->with(fn () => LinkPlatform::cases());
});

describe('reading a platform off a payload', function () {
    it('resolves the value the client sent', function (string $value, LinkPlatform $platform) {
        expect(LinkPlatform::fromValue($value))->toBe($platform);
    })->with([
        'website' => ['website', LinkPlatform::Website],
        'instagram' => ['instagram', LinkPlatform::Instagram],
        'tiktok' => ['tiktok', LinkPlatform::TikTok],
        'x' => ['x', LinkPlatform::X],
        'linkedin' => ['linkedin', LinkPlatform::LinkedIn],
        'youtube' => ['youtube', LinkPlatform::YouTube],
        'whatsapp' => ['whatsapp', LinkPlatform::WhatsApp],
    ]);

    it('forgives the casing and the padding a form leaves behind', function (string $value) {
        expect(LinkPlatform::fromValue($value))->toBe(LinkPlatform::Instagram);
    })->with([
        'upper case' => 'INSTAGRAM',
        'title case' => 'Instagram',
        'padded' => '  instagram  ',
        'padded with a tab' => "\tinstagram\n",
    ]);

    it('turns an unknown platform into a domain failure, never into a ValueError', function (string $value) {
        expect(fn () => LinkPlatform::fromValue($value))->toThrow(InvalidLinkPlatform::class)
            ->and(is_a(InvalidLinkPlatform::class, DomainFailure::class, true))->toBeTrue();
    })->with([
        'a network we do not support' => 'threads',
        'a case sensitive name' => 'TikTok Shop',
        'the display name' => 'Twitter',
        'a url instead of a platform' => 'https://instagram.com',
        'empty' => '',
        'whitespace' => '   ',
    ]);

    it('quotes the value it refused, untouched by the normalisation', function () {
        expect(fn () => LinkPlatform::fromValue('  Threads '))
            ->toThrow(InvalidLinkPlatform::class, '[  Threads ] is not a platform a link may point at.');
    });
});

describe('which hosts a platform owns', function () {
    it('lets a website point anywhere', function (string $host) {
        expect(LinkPlatform::Website->acceptsAnyHost())->toBeTrue()
            ->and(LinkPlatform::Website->accepts($host))->toBeTrue();
    })->with([
        'a custom domain' => 'ada-salon.mx',
        'a subdomain' => 'www.ada-salon.mx',
        'another network' => 'instagram.com',
    ]);

    it('lets no other platform point anywhere', function (LinkPlatform $platform) {
        expect($platform->acceptsAnyHost())->toBeFalse();
    })->with(fn () => array_values(array_filter(
        LinkPlatform::cases(),
        static fn (LinkPlatform $platform): bool => $platform !== LinkPlatform::Website,
    )));

    it('accepts the host that belongs to the platform', function (LinkPlatform $platform, string $host) {
        expect($platform->accepts($host))->toBeTrue();
    })->with([
        'instagram' => [LinkPlatform::Instagram, 'instagram.com'],
        'facebook' => [LinkPlatform::Facebook, 'facebook.com'],
        'a facebook short link' => [LinkPlatform::Facebook, 'fb.me'],
        'tiktok' => [LinkPlatform::TikTok, 'tiktok.com'],
        'x' => [LinkPlatform::X, 'x.com'],
        'x under its old name' => [LinkPlatform::X, 'twitter.com'],
        'linkedin' => [LinkPlatform::LinkedIn, 'linkedin.com'],
        'youtube' => [LinkPlatform::YouTube, 'youtube.com'],
        'a youtube short link' => [LinkPlatform::YouTube, 'youtu.be'],
        'whatsapp' => [LinkPlatform::WhatsApp, 'whatsapp.com'],
        'a whatsapp short link' => [LinkPlatform::WhatsApp, 'wa.me'],
    ]);

    it('accepts a subdomain of a host it owns', function (LinkPlatform $platform, string $host) {
        expect($platform->accepts($host))->toBeTrue();
    })->with([
        'a regional linkedin' => [LinkPlatform::LinkedIn, 'mx.linkedin.com'],
        'a mobile facebook' => [LinkPlatform::Facebook, 'm.facebook.com'],
        'a nested subdomain' => [LinkPlatform::YouTube, 'music.gaming.youtube.com'],
    ]);

    it('refuses a look-alike host that merely ends in the same letters', function (LinkPlatform $platform, string $host) {
        expect($platform->accepts($host))->toBeFalse();
    })->with([
        'a prefixed impostor' => [LinkPlatform::Instagram, 'notinstagram.com'],
        'a hyphenated impostor' => [LinkPlatform::Instagram, 'evil-instagram.com'],
        'a different tld' => [LinkPlatform::Instagram, 'instagram.com.mx'],
        'the platform as a path on another host' => [LinkPlatform::TikTok, 'phishing.test'],
        'another network entirely' => [LinkPlatform::X, 'facebook.com'],
        'an empty host' => [LinkPlatform::Instagram, ''],
    ]);

    it('lists no host suffix for a website, because it owns none', function () {
        expect(LinkPlatform::Website->hostSuffixes())->toBe([]);
    });

    it('lists at least one host suffix for every other platform', function (LinkPlatform $platform) {
        expect($platform->hostSuffixes())->not->toBeEmpty();
    })->with(fn () => array_values(array_filter(
        LinkPlatform::cases(),
        static fn (LinkPlatform $platform): bool => $platform !== LinkPlatform::Website,
    )));
});
