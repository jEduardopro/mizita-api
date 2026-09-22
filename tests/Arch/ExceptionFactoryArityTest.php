<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Tests\Support\Architecture\ArityFixtureFailure;
use Tests\Support\Architecture\MiscalledFactoryCalls;

function mizitaProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * @return list<class-string>
 */
function mizitaDomainFailureClasses(): array
{
    $paths = [
        ...glob(mizitaProjectRoot().'/app/Domains/*/Exceptions/*.php') ?: [],
        ...glob(mizitaProjectRoot().'/app/Shared/Exceptions/*.php') ?: [],
    ];

    $classes = [];

    foreach ($paths as $path) {
        $relative = substr($path, strlen(mizitaProjectRoot().'/app/'), -strlen('.php'));
        $class = 'App\\'.str_replace('/', '\\', $relative);

        if (class_exists($class)) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

/**
 * @param  class-string  ...$classes
 * @return array<class-string, array<string, array{int, int}>>
 */
function mizitaFactoriesOf(string ...$classes): array
{
    $factories = [];

    foreach ($classes as $class) {
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            $factories[$class][$method->getName()] = [
                $method->getNumberOfRequiredParameters(),
                $method->isVariadic() ? PHP_INT_MAX : $method->getNumberOfParameters(),
            ];
        }
    }

    return $factories;
}

/**
 * @return list<string>
 */
function mizitaScannedSources(): array
{
    $paths = [];

    foreach (['app', 'tests'] as $directory) {
        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(mizitaProjectRoot().'/'.$directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }
    }

    sort($paths);

    return $paths;
}

/**
 * @return array<string, string>
 */
function mizitaAliasesIn(string $source): array
{
    preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?\s*;/mi', $source, $matches, PREG_SET_ORDER);

    $aliases = [];

    foreach ($matches as $match) {
        $imported = ltrim($match[1], '\\');
        $alias = ($match[2] ?? '') !== '' ? $match[2] : substr((string) strrchr('\\'.$imported, '\\'), 1);
        $aliases[$alias] = $imported;
    }

    return $aliases;
}

function mizitaNamespaceOf(string $source): string
{
    return preg_match('/^namespace\s+([A-Za-z0-9_\\\\]+)\s*;/m', $source, $match) === 1 ? $match[1] : '';
}

/**
 * @param  array<string, string>  $aliases
 * @return list<string>
 */
function mizitaCandidateClassesFor(string $name, array $aliases, string $namespace): array
{
    $absolute = ltrim($name, '\\');

    if (str_starts_with($name, '\\')) {
        return [$absolute];
    }

    $segments = explode('\\', $absolute);
    $first = array_shift($segments);
    $candidates = [];

    if (isset($aliases[$first])) {
        $candidates[] = implode('\\', [$aliases[$first], ...$segments]);
    }

    if ($namespace !== '') {
        $candidates[] = $namespace.'\\'.$absolute;
    }

    $candidates[] = $absolute;

    return $candidates;
}

function mizitaWithoutLiterals(string $line): string
{
    $line = preg_replace('/\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"/', "''", $line) ?? $line;
    $comment = strpos($line, '//');

    return $comment === false ? $line : substr($line, 0, $comment);
}

function mizitaBalancedArgumentsAt(string $line, int $openingParen): ?string
{
    $depth = 0;
    $length = strlen($line);

    for ($position = $openingParen; $position < $length; $position++) {
        if ($line[$position] === '(') {
            $depth++;

            continue;
        }

        if ($line[$position] !== ')') {
            continue;
        }

        if (--$depth === 0) {
            return substr($line, $openingParen + 1, $position - $openingParen - 1);
        }
    }

    return null;
}

function mizitaArgumentCountOf(string $arguments): ?int
{
    $arguments = trim($arguments);

    if ($arguments === '') {
        return 0;
    }

    if (str_contains($arguments, '...')) {
        return null;
    }

    $depth = 0;
    $count = 1;

    foreach (str_split($arguments) as $character) {
        if (in_array($character, ['(', '[', '{'], true)) {
            $depth++;
        } elseif (in_array($character, [')', ']', '}'], true)) {
            $depth--;
        } elseif ($character === ',' && $depth === 0) {
            $count++;
        }
    }

    return $depth === 0 ? $count : null;
}

/**
 * @param  array<class-string, array<string, array{int, int}>>  $factories
 * @return list<array{class: string, method: string, given: int, minimum: int, maximum: int, path: string, line: int}>
 */
function mizitaFactoryCallsIn(string $path, array $factories): array
{
    $source = (string) file_get_contents($path);

    if (! preg_match('/::[a-zA-Z_]/', $source)) {
        return [];
    }

    $aliases = mizitaAliasesIn($source);
    $namespace = mizitaNamespaceOf($source);
    $pattern = '/((?:\\\\)?[A-Z][A-Za-z0-9_]*(?:\\\\[A-Za-z0-9_]+)*)::([a-zA-Z_][A-Za-z0-9_]*)\s*\(/';
    $calls = [];

    foreach (explode("\n", $source) as $index => $rawLine) {
        $line = mizitaWithoutLiterals($rawLine);

        if (! preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $match) {
            $method = $match[2][0];
            $class = null;

            foreach (mizitaCandidateClassesFor($match[1][0], $aliases, $namespace) as $candidate) {
                if (isset($factories[$candidate][$method])) {
                    $class = $candidate;

                    break;
                }
            }

            if ($class === null) {
                continue;
            }

            $arguments = mizitaBalancedArgumentsAt($line, $match[0][1] + strlen($match[0][0]) - 1);

            if ($arguments === null) {
                continue;
            }

            $given = mizitaArgumentCountOf($arguments);

            if ($given === null) {
                continue;
            }

            [$minimum, $maximum] = $factories[$class][$method];

            $calls[] = [
                'class' => $class,
                'method' => $method,
                'given' => $given,
                'minimum' => $minimum,
                'maximum' => $maximum,
                'path' => substr($path, strlen(mizitaProjectRoot()) + 1),
                'line' => $index + 1,
            ];
        }
    }

    return $calls;
}

function mizitaArityRangeOf(int $minimum, int $maximum): string
{
    if ($maximum === PHP_INT_MAX) {
        return "at least {$minimum}";
    }

    return $minimum === $maximum ? "exactly {$minimum}" : "{$minimum} to {$maximum}";
}

/**
 * @param  array{class: string, method: string, given: int, minimum: int, maximum: int, path: string, line: int}  $call
 */
function mizitaDescribeArityOffence(array $call): string
{
    return sprintf(
        '%s::%s() is handed %d %s at %s:%d, and accepts %s.',
        $call['class'],
        $call['method'],
        $call['given'],
        $call['given'] === 1 ? 'argument' : 'arguments',
        $call['path'],
        $call['line'],
        mizitaArityRangeOf($call['minimum'], $call['maximum']),
    );
}

it('hands every domain failure factory the number of arguments it declares', function () {
    $factories = mizitaFactoriesOf(...mizitaDomainFailureClasses());
    $calls = [];
    $offenders = [];

    foreach (mizitaScannedSources() as $path) {
        foreach (mizitaFactoryCallsIn($path, $factories) as $call) {
            $calls[] = $call;

            if ($call['given'] < $call['minimum'] || $call['given'] > $call['maximum']) {
                $offenders[] = mizitaDescribeArityOffence($call);
            }
        }
    }

    Assert::assertNotEmpty(
        $factories,
        'No exception declares a static factory, so this rule is not checking anything. Confirm Exceptions/ still lives under app/Domains/*/ and app/Shared/.',
    );

    Assert::assertNotEmpty(
        $calls,
        'No factory call site was found under app/ or tests/, so this rule is not checking anything. Confirm the scan still resolves imported class names.',
    );

    Assert::assertSame(
        [],
        $offenders,
        "This project runs no static analyser, and PHP only raises ArgumentCountError when the line runs, so a miscounted factory call on a refusal path ships as a 500 where a translated 422 was owed:\n  - "
        .implode("\n  - ", $offenders),
    );
});

describe('the detector behind the rule above, shown on call sites written to break it', function () {
    /**
     * @return list<array{class: string, method: string, given: int, minimum: int, maximum: int, path: string, line: int}>
     */
    function mizitaFixtureCalls(): array
    {
        return mizitaFactoryCallsIn(
            (string) (new ReflectionClass(MiscalledFactoryCalls::class))->getFileName(),
            mizitaFactoriesOf(ArityFixtureFailure::class),
        );
    }

    /**
     * @return list<string>
     */
    function mizitaFixtureSignatures(): array
    {
        return array_map(
            static fn (array $call): string => sprintf('%s:%d given %d', $call['method'], $call['line'], $call['given']),
            mizitaFixtureCalls(),
        );
    }

    it('reads the required and total parameter counts off each factory', function () {
        expect(mizitaFactoriesOf(ArityFixtureFailure::class))->toBe([
            ArityFixtureFailure::class => [
                'withOne' => [1, 1],
                'withBounds' => [2, 2],
                'withOptional' => [1, 2],
            ],
        ]);
    });

    it('resolves a call site through the imports of the file it sits in', function () {
        expect(mizitaFixtureCalls())->not->toBeEmpty()
            ->and(array_unique(array_column(mizitaFixtureCalls(), 'class')))
            ->toBe([ArityFixtureFailure::class]);
    });

    it('spots a call that forgets a required argument', function () {
        $offenders = array_values(array_filter(
            mizitaFixtureCalls(),
            static fn (array $call): bool => $call['given'] < $call['minimum'] || $call['given'] > $call['maximum'],
        ));

        expect(array_map(
            static fn (array $call): string => $call['method'].' given '.$call['given'],
            $offenders,
        ))->toBe([
            'withBounds given 1',
            'withOne given 0',
            'withOne given 2',
        ]);
    });

    it('names the class, the count, the range and the place in its failure message', function () {
        $offence = mizitaDescribeArityOffence([
            'class' => ArityFixtureFailure::class,
            'method' => 'withBounds',
            'given' => 1,
            'minimum' => 2,
            'maximum' => 2,
            'path' => 'tests/Support/Architecture/MiscalledFactoryCalls.php',
            'line' => 27,
        ]);

        expect($offence)->toBe(
            'Tests\Support\Architecture\ArityFixtureFailure::withBounds() is handed 1 argument'
            .' at tests/Support/Architecture/MiscalledFactoryCalls.php:27, and accepts exactly 2.'
        );
    });

    it('describes an optional parameter as a range and a variadic one as a floor', function () {
        expect(mizitaArityRangeOf(1, 2))->toBe('1 to 2')
            ->and(mizitaArityRangeOf(1, PHP_INT_MAX))->toBe('at least 1')
            ->and(mizitaArityRangeOf(0, 0))->toBe('exactly 0');
    });

    it('records one call per line it can parse, with the count that line passes', function () {
        expect(mizitaFixtureSignatures())->toBe([
            'withBounds:11 given 2',
            'withOptional:16 given 1',
            'withBounds:21 given 2',
            'withBounds:26 given 1',
            'withOne:31 given 0',
            'withOne:36 given 2',
        ]);
    });

    it('leaves a call that omits an optional argument alone', function () {
        expect(mizitaFixtureSignatures())->toContain('withOptional:16 given 1');
    });

    it('counts a bare pair of parentheses as no arguments at all', function () {
        expect(mizitaArgumentCountOf(''))->toBe(0)
            ->and(mizitaArgumentCountOf('   '))->toBe(0);
    });

    it('counts the commas the call itself owns and not the ones nested inside it', function () {
        expect(mizitaArgumentCountOf('max(101, 0), 100'))->toBe(2)
            ->and(mizitaArgumentCountOf("['a', 'b'], 100"))->toBe(2);
    });

    it('refuses to guess at an argument list whose parentheses do not balance', function () {
        expect(mizitaArgumentCountOf('101, max(0'))->toBeNull()
            ->and(mizitaBalancedArgumentsAt('Failure::withBounds(101,', 19))->toBeNull();
    });

    it('refuses to guess at an argument list a spread operator fills', function () {
        expect(mizitaArgumentCountOf('...$arguments'))->toBeNull();
    });

    it('skips a call whose arguments are spread over several lines', function () {
        expect(array_column(mizitaFixtureCalls(), 'line'))->not->toContain(41);
    });

    it('never mistakes a factory named inside a string for a call to it', function () {
        expect(mizitaWithoutLiterals("return 'ArityFixtureFailure::withBounds() wants two';"))
            ->toBe("return '';")
            ->and(array_column(mizitaFixtureCalls(), 'line'))
            ->not->toContain(49);
    });
});
