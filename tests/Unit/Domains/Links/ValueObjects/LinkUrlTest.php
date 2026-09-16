<?php

declare(strict_types=1);

use App\Domains\Links\Exceptions\InvalidLinkUrl;
use App\Domains\Links\Exceptions\LinkPlatformMismatch;
use App\Domains\Links\ValueObjects\LinkPlatform;
use App\Domains\Links\ValueObjects\LinkUrl;

describe('a url that belongs to its platform', function () {
    it('accepts the address the network hands a business', function (LinkPlatform $platform, string $url) {
        expect(LinkUrl::forPlatform($platform, $url)->value)->toBe($url);
    })->with([
        'instagram' => [LinkPlatform::Instagram, 'https://instagram.com/ada.salon'],
        'instagram with the www' => [LinkPlatform::Instagram, 'https://www.instagram.com/ada.salon'],
        'facebook' => [LinkPlatform::Facebook, 'https://facebook.com/ada.salon'],
        'a facebook short link' => [LinkPlatform::Facebook, 'https://fb.me/adasalon'],
        'tiktok' => [LinkPlatform::TikTok, 'https://www.tiktok.com/@ada.salon'],
        'x' => [LinkPlatform::X, 'https://x.com/adasalon'],
        'x under its old name' => [LinkPlatform::X, 'https://twitter.com/adasalon'],
        'a regional linkedin' => [LinkPlatform::LinkedIn, 'https://mx.linkedin.com/company/ada-salon'],
        'a youtube short link' => [LinkPlatform::YouTube, 'https://youtu.be/dQw4w9WgXcQ'],
        'whatsapp' => [LinkPlatform::WhatsApp, 'https://wa.me/525512345678'],
    ]);

    it('lets a website point at any host, which is the whole point of the case', function (string $url) {
        expect(LinkUrl::forPlatform(LinkPlatform::Website, $url)->value)->toBe($url);
    })->with([
        'a custom domain' => 'https://ada-salon.mx',
        'a subdomain' => 'https://citas.ada-salon.mx/reservar',
        'plain http' => 'http://ada-salon.mx',
        'a host that belongs to a network' => 'https://instagram.com/ada.salon',
        'a port and a query' => 'https://ada-salon.mx:8443/reservar?utm=qr',
    ]);

    it('keeps the url exactly as the caller typed it, case and path included', function () {
        expect(LinkUrl::forPlatform(LinkPlatform::Instagram, 'https://Instagram.com/Ada.Salon')->value)
            ->toBe('https://Instagram.com/Ada.Salon');
    });

    it('trims the padding a form leaves around it', function () {
        expect(LinkUrl::forPlatform(LinkPlatform::Instagram, "  https://instagram.com/ada.salon \n")->value)
            ->toBe('https://instagram.com/ada.salon');
    });

    it('accepts a url exactly as long as the column allows', function () {
        $url = 'https://ada-salon.mx/'.str_repeat('a', LinkUrl::MAXIMUM_LENGTH - 21);

        expect(LinkUrl::forPlatform(LinkPlatform::Website, $url)->value)
            ->toHaveLength(LinkUrl::MAXIMUM_LENGTH);
    });
});

describe('a url that is not a url', function () {
    it('refuses one that carries nothing', function (string $url) {
        expect(fn () => LinkUrl::forPlatform(LinkPlatform::Website, $url))
            ->toThrow(InvalidLinkUrl::class, 'A link url cannot be empty.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
    ]);

    it('refuses one digit past the maximum length', function () {
        $url = 'https://ada-salon.mx/'.str_repeat('a', LinkUrl::MAXIMUM_LENGTH - 20);

        expect(fn () => LinkUrl::forPlatform(LinkPlatform::Website, $url))
            ->toThrow(InvalidLinkUrl::class, 'The url offered is longer than a link url may be.');
    });

    it('refuses a scheme the browser would not follow to a web page', function (string $url) {
        expect(fn () => LinkUrl::forPlatform(LinkPlatform::Website, $url))
            ->toThrow(InvalidLinkUrl::class, 'A link url must be served over http or https.');
    })->with([
        'javascript' => 'javascript:alert(1)',
        'a mail link' => 'mailto:hola@ada-salon.mx',
        'a phone link' => 'tel:+525512345678',
        'ftp' => 'ftp://ada-salon.mx/logo.png',
        'a data url' => 'data:text/html;base64,PHNjcmlwdD4=',
        'no scheme at all' => 'ada-salon.mx',
        'a protocol relative url' => '//ada-salon.mx',
        'a path only' => '/reservar',
    ]);

    it('accepts either scheme a web page is served over', function (string $url) {
        expect(LinkUrl::forPlatform(LinkPlatform::Website, $url)->value)->toBe($url);
    })->with([
        'https' => 'https://ada-salon.mx',
        'http' => 'http://ada-salon.mx',
        'https shouted' => 'HTTPS://ada-salon.mx',
    ]);

    it('refuses a url with no host a person could visit', function (string $url) {
        expect(fn () => LinkUrl::forPlatform(LinkPlatform::Website, $url))
            ->toThrow(InvalidLinkUrl::class, 'The url offered is not a readable web address.');
    })->with([
        'a bare hostname with no dot' => 'https://localhost',
        'an intranet name' => 'http://intranet/reservar',
        'no host at all' => 'https:///reservar',
        'a port with no host' => 'http://:80',
    ]);
});

describe('a url that belongs to another platform', function () {
    it('refuses a host the platform does not own', function (LinkPlatform $platform, string $url) {
        expect(fn () => LinkUrl::forPlatform($platform, $url))->toThrow(LinkPlatformMismatch::class);
    })->with([
        'a facebook page filed under instagram' => [LinkPlatform::Instagram, 'https://facebook.com/ada.salon'],
        'a custom domain filed under tiktok' => [LinkPlatform::TikTok, 'https://ada-salon.mx/tiktok'],
        'a look-alike host' => [LinkPlatform::Instagram, 'https://evil-instagram.com/ada.salon'],
        'a prefixed impostor' => [LinkPlatform::Instagram, 'https://notinstagram.com/ada.salon'],
        'the platform only in the path' => [LinkPlatform::YouTube, 'https://phishing.test/youtube.com'],
        'a different tld' => [LinkPlatform::X, 'https://x.com.mx/adasalon'],
    ]);

    it('names the platform and the host it could not reconcile', function () {
        expect(fn () => LinkUrl::forPlatform(LinkPlatform::Instagram, 'https://www.facebook.com/ada.salon'))
            ->toThrow(
                LinkPlatformMismatch::class,
                'Host [facebook.com] does not belong to the [instagram] platform.',
            );
    });

    it('checks the length before it ever looks at the host', function () {
        $url = 'https://facebook.com/'.str_repeat('a', LinkUrl::MAXIMUM_LENGTH);

        expect(fn () => LinkUrl::forPlatform(LinkPlatform::Instagram, $url))
            ->toThrow(InvalidLinkUrl::class, 'The url offered is longer than a link url may be.');
    });
});

describe('a url read back from persistence', function () {
    it('restores a stored url without asking the rules again', function () {
        expect(LinkUrl::restore('not-a-url')->value)->toBe('not-a-url');
    });

    it('is equal to another url with the same text', function () {
        expect(LinkUrl::forPlatform(LinkPlatform::Website, 'https://ada-salon.mx')
            ->equals(LinkUrl::restore('https://ada-salon.mx')))->toBeTrue();
    });

    it('is not equal to a url that differs in any character', function (string $other) {
        expect(LinkUrl::forPlatform(LinkPlatform::Website, 'https://ada-salon.mx')
            ->equals(LinkUrl::restore($other)))->toBeFalse();
    })->with([
        'a trailing slash' => 'https://ada-salon.mx/',
        'the other scheme' => 'http://ada-salon.mx',
        'the www' => 'https://www.ada-salon.mx',
        'another case' => 'https://Ada-Salon.mx',
    ]);
});
