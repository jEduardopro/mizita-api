<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Media\SafeFileName;

describe('the shape of the helper', function () {
    it('is final, so no adapter can subclass a variant of the naming rule', function () {
        expect((new ReflectionClass(SafeFileName::class))->isFinal())->toBeTrue();
    });

    it('exposes one static entry point taking the name and the caller fallback', function () {
        $public = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(SafeFileName::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        $from = new ReflectionMethod(SafeFileName::class, 'from');

        expect($public)->toBe(['from'])
            ->and($from->isStatic())->toBeTrue()
            ->and((string) $from->getReturnType())->toBe('string')
            ->and(array_map(
                static fn (ReflectionParameter $parameter): string => $parameter->getName().':'.$parameter->getType(),
                $from->getParameters(),
            ))->toBe(['fileName:string', 'fallbackName:string']);
    });

    it('holds no state, so it needs no construction', function () {
        expect((new ReflectionClass(SafeFileName::class))->getProperties())->toBe([]);
    });
});

describe('the file name it hands back', function () {
    it('keeps a plain name, lowercased', function () {
        expect(SafeFileName::from('Logo.PNG', 'fallback'))->toBe('logo.png');
    });

    it('lowercases the extension as well as the stem', function () {
        expect(SafeFileName::from('corte.JPEG', 'fallback'))->toBe('corte.jpeg');
    });

    it('keeps a name that carries no extension at all', function () {
        expect(SafeFileName::from('Banner', 'fallback'))->toBe('banner');
    });

    it('strips the path a client sent, so nothing can escape the media directory', function (string $fileName) {
        $safe = SafeFileName::from($fileName, 'fallback');

        expect($safe)->not->toContain('/')
            ->and($safe)->not->toContain('..')
            ->and($safe)->not->toContain('\\');
    })->with([
        'traversal' => '../../etc/passwd',
        'absolute path' => '/etc/passwd',
        'nested' => 'uploads/2026/logo.png',
    ]);

    it('collapses every run of unsupported characters into a single separator', function (string $fileName, string $expected) {
        expect(SafeFileName::from($fileName, 'fallback'))->toBe($expected);
    })->with([
        'spaces and punctuation' => ['mi   logo!!!final.JPEG', 'mi-logo-final.jpeg'],
        'leading and trailing noise' => ['---corte---.png', 'corte.png'],
        'underscores' => ['mi_logo_final.png', 'mi-logo-final.png'],
    ]);

    it('truncates a stem longer than the column tolerates, keeping the extension', function () {
        expect(SafeFileName::from(str_repeat('a', 200).'.png', 'fallback'))->toBe(str_repeat('a', 80).'.png');
    });

    it('measures the truncation in characters, not bytes', function () {
        expect(mb_strlen(explode('.', SafeFileName::from(str_repeat('a', 200).'.png', 'fallback'))[0]))->toBe(80);
    });

    it('transliterates the accents and the ñ a Mexican upload is ordinarily named with', function (string $fileName, string $expected) {
        expect(SafeFileName::from($fileName, 'fallback'))->toBe($expected);
    })->with([
        'accented and ñ' => ['Peluquería Ñandú.png', 'peluqueria-nandu.png'],
        'every accented vowel' => ['áéíóú.JPG', 'aeiou.jpg'],
        'ü as in pingüino' => ['Pingüino.png', 'pinguino.png'],
        'ñ alone' => ['ñ.png', 'n.png'],
        'uppercase ñ alone' => ['Ñ.png', 'n.png'],
        'ç as in Français' => ['Français.png', 'francais.png'],
    ]);
});

describe('the fallback the caller owns', function () {
    it('uses the caller name when the upload carried no usable stem', function (string $fileName, string $expected) {
        expect(SafeFileName::from($fileName, 'logo'))->toBe($expected);
    })->with([
        'extension only' => ['.png', 'logo.png'],
        'symbols only' => ['***', 'logo'],
        'empty' => ['', 'logo'],
        'spaces' => ['   ', 'logo'],
        'separators only' => ['---', 'logo'],
        'a stem no latin alphabet can spell' => ['日本語.png', 'logo.png'],
    ]);

    it('never hands back a name that is only an extension', function (string $fallbackName) {
        expect(SafeFileName::from('.png', $fallbackName))->toBe($fallbackName.'.png')
            ->and(SafeFileName::from('.png', $fallbackName))->not->toStartWith('.');
    })->with(['logo', 'image', 'banner']);

    it('takes the fallback from the caller rather than deciding one for every adapter', function () {
        expect(SafeFileName::from('***.png', 'logo'))->toBe('logo.png')
            ->and(SafeFileName::from('***.png', 'image'))->toBe('image.png');
    });

    it('leaves the extension alone when only the stem fell back', function () {
        expect(SafeFileName::from('日本語.JPEG', 'image'))->toBe('image.jpeg');
    });
});
