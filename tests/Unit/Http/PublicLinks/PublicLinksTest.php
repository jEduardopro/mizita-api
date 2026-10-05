<?php

declare(strict_types=1);

use App\Http\PublicLinks\PublicLinks;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, mixed>  $configured
 * @return array{facebook: ?string, instagram: ?string, contact: ?string}
 */
function describedPublicLinks(array $configured): array
{
    config(['public-links' => $configured]);

    return (new PublicLinks)->describe();
}

dataset('every public link', ['facebook', 'instagram', 'contact']);

dataset('web-only public links', ['facebook', 'instagram']);

dataset('accepted web links', [
    'https' => ['https://www.facebook.com/mizita', 'https://www.facebook.com/mizita'],
    'http' => ['http://instagram.com/mizita', 'http://instagram.com/mizita'],
    'uppercase scheme' => ['HTTPS://www.facebook.com/mizita', 'HTTPS://www.facebook.com/mizita'],
    'mixed case scheme' => ['HtTp://instagram.com/mizita', 'HtTp://instagram.com/mizita'],
    'query and fragment' => ['https://mizita.app/contact?from=landing#form', 'https://mizita.app/contact?from=landing#form'],
    'port' => ['https://mizita.app:8443/contact', 'https://mizita.app:8443/contact'],
    'unicode host' => ['https://barbería-ñandú.es/reservas', 'https://barbería-ñandú.es/reservas'],
    'surrounding spaces' => ['   https://www.facebook.com/mizita   ', 'https://www.facebook.com/mizita'],
    'surrounding tabs and newlines' => ["\t\nhttps://instagram.com/mizita\r\n", 'https://instagram.com/mizita'],
]);

dataset('values no public link may carry', [
    'null' => [null],
    'empty string' => [''],
    'spaces only' => ['   '],
    'tabs and newlines only' => ["\t\n\r"],
    'an integer' => [42],
    'a float' => [3.14],
    'true' => [true],
    'false' => [false],
    'an array holding a url' => [['https://www.facebook.com/mizita']],
    'a space inside' => ['https://www.face book.com/mizita'],
    'a newline inside' => ["https://www.facebook.com/\nmizita"],
    'a tab inside' => ["https://www.facebook.com/\tmizita"],
    'a carriage return inside' => ["https://www.facebook.com/\rmizita"],
    'a null byte inside' => ["https://www.facebook.com/\0mizita"],
    'a bell character inside' => ["https://www.facebook.com/\x07mizita"],
    'a delete character inside' => ["https://www.facebook.com/\x7Fmizita"],
    'a no-break space inside' => ["https://www.facebook.com/\u{00A0}mizita"],
    'malformed utf-8' => ["https://www.facebook.com/\xC3\x28"],
    'a newline splitting javascript' => ["java\nscript:alert(1)"],
    'javascript' => ['javascript:alert(1)'],
    'uppercase javascript' => ['JAVASCRIPT:alert(1)'],
    'javascript with an authority and an encoded newline' => ['javascript://x%0Aalert(1)'],
    'vbscript' => ['vbscript:msgbox(1)'],
    'data' => ['data:text/html,<script>alert(1)</script>'],
    'file' => ['file:///etc/passwd'],
    'ftp' => ['ftp://x.com'],
    'tel' => ['tel:+34600000000'],
    'https without a host' => ['https:nohost'],
    'https with an empty authority' => ['https://'],
    'scheme relative' => ['//www.facebook.com/mizita'],
    'no scheme' => ['www.facebook.com/mizita'],
    'a relative path' => ['/contact'],
    'a bare mailto' => ['mailto:'],
    'a bare mailto with surrounding spaces' => ['  mailto:  '],
    'a mailto with a space inside' => ['mailto:hola@mizita.app?subject=Hola mundo'],
]);

beforeEach(function () {
    config(['public-links' => ['facebook' => null, 'instagram' => null, 'contact' => null]]);
});

it('describes exactly the facebook, instagram and contact links, in that order', function () {
    expect(array_keys((new PublicLinks)->describe()))->toBe(['facebook', 'instagram', 'contact']);
});

it('reads each link from its own configuration key', function () {
    expect(describedPublicLinks([
        'facebook' => 'https://www.facebook.com/mizita',
        'instagram' => 'https://www.instagram.com/mizita',
        'contact' => 'mailto:hola@mizita.app',
    ]))->toBe([
        'facebook' => 'https://www.facebook.com/mizita',
        'instagram' => 'https://www.instagram.com/mizita',
        'contact' => 'mailto:hola@mizita.app',
    ]);
});

it('describes every link as null when none is configured', function () {
    expect(describedPublicLinks([]))->toBe(['facebook' => null, 'instagram' => null, 'contact' => null]);
});

it('keeps the valid links when another one is rejected', function () {
    expect(describedPublicLinks([
        'facebook' => 'javascript:alert(1)',
        'instagram' => 'https://www.instagram.com/mizita',
        'contact' => 'mailto:hola@mizita.app',
    ]))->toBe([
        'facebook' => null,
        'instagram' => 'https://www.instagram.com/mizita',
        'contact' => 'mailto:hola@mizita.app',
    ]);
});

describe('every link', function () {
    it('accepts an http or https url with a host, trimmed', function (string $link, string $configured, string $expected) {
        expect(describedPublicLinks([$link => $configured])[$link])->toBe($expected);
    })->with('every public link')->with('accepted web links');

    it('is null when its key is missing from the configuration', function (string $link) {
        $everyLinkButThisOne = array_diff_key(
            ['facebook' => 'https://a.example', 'instagram' => 'https://b.example', 'contact' => 'https://c.example'],
            [$link => true],
        );

        $described = describedPublicLinks($everyLinkButThisOne);

        expect($described)->toHaveKey($link)
            ->and($described[$link])->toBeNull();
    })->with('every public link');

    it('is null for a value no public link may carry', function (string $link, mixed $configured) {
        expect(describedPublicLinks([$link => $configured])[$link])->toBeNull();
    })->with('every public link')->with('values no public link may carry');
});

describe('the facebook and instagram links', function () {
    it('rejects a mailto address', function (string $link, string $configured) {
        expect(describedPublicLinks([$link => $configured])[$link])->toBeNull();
    })->with('web-only public links')->with([
        'mailto' => 'mailto:hola@mizita.app',
        'uppercase mailto' => 'MAILTO:hola@mizita.app',
    ]);
});

describe('the contact link', function () {
    it('accepts mailto followed by an address, trimmed', function (string $configured, string $expected) {
        expect(describedPublicLinks(['contact' => $configured])['contact'])->toBe($expected);
    })->with([
        'mailto' => ['mailto:hola@mizita.app', 'mailto:hola@mizita.app'],
        'uppercase scheme' => ['MAILTO:hola@mizita.app', 'MAILTO:hola@mizita.app'],
        'mixed case scheme' => ['MailTo:hola@mizita.app', 'MailTo:hola@mizita.app'],
        'with a subject' => ['mailto:hola@mizita.app?subject=Hola', 'mailto:hola@mizita.app?subject=Hola'],
        'accented address' => ['mailto:soporte@peluquería.es', 'mailto:soporte@peluquería.es'],
        'surrounding spaces' => ['  mailto:hola@mizita.app  ', 'mailto:hola@mizita.app'],
    ]);
});
