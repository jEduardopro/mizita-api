<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Media\SafeFileName;
use Tests\Support\Shared\ImageFiles;

describe('the shape of the helper', function () {
    it('is final, so no adapter can subclass a variant of the naming rule', function () {
        expect((new ReflectionClass(SafeFileName::class))->isFinal())->toBeTrue();
    });

    it('exposes one static entry point taking the stored file, the client name and the caller fallback', function () {
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
            ))->toBe(['sourcePath:string', 'fileName:string', 'fallbackName:string']);
    });

    it('holds no state, so it needs no construction', function () {
        expect((new ReflectionClass(SafeFileName::class))->getProperties())->toBe([]);
    });
});

describe('the extension it hands back', function () {
    it('takes the extension from the sniffed content of the file', function (Closure $source, string $extension) {
        expect(SafeFileName::from($source(), 'corte.JPEG', 'fallback'))->toBe('corte.'.$extension);
    })->with([
        'a jpeg is stored as jpg' => [ImageFiles::jpeg(...), 'jpg'],
        'a png' => [ImageFiles::png(...), 'png'],
        'a webp' => [ImageFiles::webp(...), 'webp'],
    ]);

    it('ignores the extension the client claimed', function (string $fileName, string $expected) {
        expect(SafeFileName::from(ImageFiles::png(), $fileName, 'fallback'))->toBe($expected);
    })->with([
        'a png named as a jpeg' => ['photo.jpg', 'photo.png'],
        'a png named as a script' => ['shell.php', 'shell.png'],
        'a png hiding a script behind a double extension' => ['shell.php.jpg', 'shell-php.png'],
        'a png named with no extension at all' => ['Banner', 'banner.png'],
        'an uppercase extension' => ['Logo.PNG', 'logo.png'],
    ]);

    it('never lets a script extension through', function () {
        expect(SafeFileName::from(ImageFiles::png(), 'shell.php.jpg', 'fallback'))->not->toContain('.php');
    });

    it('refuses content that is not an accepted image', function (Closure $source) {
        expect(fn () => SafeFileName::from($source(), 'logo.png', 'logo'))->toThrow(InvalidArgumentException::class);
    })->with([
        'a gif' => ImageFiles::gif(...),
        'an svg' => ImageFiles::svg(...),
        'plain text named as a png' => ImageFiles::text(...),
        'a php script named as a png' => static fn (): string => ImageFiles::text('<?php echo "owned";'),
    ]);

    it('refuses a source file that does not exist', function () {
        expect(fn () => SafeFileName::from(sys_get_temp_dir().'/mizita-missing-upload', 'logo.png', 'logo'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('the stem it hands back', function () {
    it('keeps a plain name, lowercased', function () {
        expect(SafeFileName::from(ImageFiles::png(), 'Logo.png', 'fallback'))->toBe('logo.png');
    });

    it('strips the path a client sent, so nothing can escape the media directory', function (string $fileName) {
        $safe = SafeFileName::from(ImageFiles::png(), $fileName, 'fallback');

        expect($safe)->not->toContain('/')
            ->and($safe)->not->toContain('..')
            ->and($safe)->not->toContain('\\');
    })->with([
        'traversal' => '../../etc/passwd',
        'absolute path' => '/etc/passwd',
        'nested' => 'uploads/2026/logo.png',
        'windows traversal' => '..\\..\\windows\\system32.png',
    ]);

    it('collapses every run of unsupported characters into a single separator', function (string $fileName, string $expected) {
        expect(SafeFileName::from(ImageFiles::png(), $fileName, 'fallback'))->toBe($expected);
    })->with([
        'spaces and punctuation' => ['mi   logo!!!final.png', 'mi-logo-final.png'],
        'leading and trailing noise' => ['---corte---.png', 'corte.png'],
        'underscores' => ['mi_logo_final.png', 'mi-logo-final.png'],
    ]);

    it('truncates a stem longer than the column tolerates, keeping the extension', function () {
        expect(SafeFileName::from(ImageFiles::png(), str_repeat('a', 200).'.png', 'fallback'))->toBe(str_repeat('a', 80).'.png');
    });

    it('keeps a stem of exactly the maximum length whole', function () {
        expect(SafeFileName::from(ImageFiles::png(), str_repeat('a', 80).'.png', 'fallback'))->toBe(str_repeat('a', 80).'.png');
    });

    it('transliterates the accents and the ñ a Mexican upload is ordinarily named with', function (string $fileName, string $expected) {
        expect(SafeFileName::from(ImageFiles::png(), $fileName, 'fallback'))->toBe($expected);
    })->with([
        'accented and ñ' => ['Peluquería Ñandú.png', 'peluqueria-nandu.png'],
        'every accented vowel' => ['áéíóú.png', 'aeiou.png'],
        'ü as in pingüino' => ['Pingüino.png', 'pinguino.png'],
        'ñ alone' => ['ñ.png', 'n.png'],
        'uppercase ñ alone' => ['Ñ.png', 'n.png'],
        'ç as in Français' => ['Français.png', 'francais.png'],
    ]);
});

describe('the fallback the caller owns', function () {
    it('uses the caller name when the upload carried no usable stem', function (string $fileName) {
        expect(SafeFileName::from(ImageFiles::png(), $fileName, 'logo'))->toBe('logo.png');
    })->with([
        'extension only' => '.png',
        'symbols only' => '***',
        'empty' => '',
        'spaces' => '   ',
        'separators only' => '---',
        'a stem no latin alphabet can spell' => '日本語.png',
    ]);

    it('takes the fallback from the caller rather than deciding one for every adapter', function () {
        expect(SafeFileName::from(ImageFiles::png(), '***.png', 'logo'))->toBe('logo.png')
            ->and(SafeFileName::from(ImageFiles::png(), '***.png', 'image'))->toBe('image.png');
    });

    it('still takes the extension from the content when only the stem fell back', function () {
        expect(SafeFileName::from(ImageFiles::jpeg(), '日本語.png', 'image'))->toBe('image.jpg');
    });
});
