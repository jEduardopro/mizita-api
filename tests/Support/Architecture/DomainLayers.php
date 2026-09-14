<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Reads the domain tree off disk so the architecture rules apply to whatever is
 * in the repository, not to a list someone has to remember to update.
 *
 * A class rather than test-file functions because arch expectations are
 * evaluated while the files are being loaded, and Pest loads them in
 * alphabetical order: a helper defined in one test file is not yet available
 * to another.
 */
final class DomainLayers
{
    /** The layers that make up the domain layer proper - plain PHP, no framework. */
    public const DOMAIN = ['Contracts', 'Entities', 'ValueObjects', 'Services', 'Events', 'Exceptions'];

    /**
     * @return list<string>
     */
    public static function domainNames(): array
    {
        $names = array_map(basename(...), glob(self::domainsDir().'/*', GLOB_ONLYDIR) ?: []);

        sort($names);

        return array_values($names);
    }

    /**
     * @return list<string>
     */
    public static function domain(): array
    {
        return self::namespacesFor(...self::DOMAIN);
    }

    /**
     * @return list<string>
     */
    public static function application(): array
    {
        return self::namespacesFor('Application');
    }

    /**
     * @return list<string>
     */
    public static function infrastructure(): array
    {
        return self::namespacesFor('Infrastructure');
    }

    /**
     * @return list<string>
     */
    public static function eloquentModels(): array
    {
        return self::namespacesFor('Infrastructure\Eloquent\Models');
    }

    /**
     * Resolves the given sub-namespaces for every domain, keeping only those
     * that exist: an arch expectation over an empty namespace fails, and "this
     * domain has no ValueObjects yet" is not a failure.
     *
     * @return list<string>
     */
    public static function namespacesFor(string ...$layers): array
    {
        $namespaces = [];

        foreach (self::domainNames() as $domain) {
            foreach ($layers as $layer) {
                if (is_dir(self::domainsDir().'/'.$domain.'/'.str_replace('\\', '/', $layer))) {
                    $namespaces[] = sprintf('App\Domains\%s\%s', $domain, $layer);
                }
            }
        }

        return $namespaces;
    }

    /**
     * The non-infrastructure namespaces belonging to one domain.
     *
     * @return list<string>
     */
    public static function insideOf(string $domain): array
    {
        $prefix = 'App\Domains\\'.$domain.'\\';

        return array_values(array_filter(
            [...self::domain(), ...self::application()],
            static fn (string $namespace): bool => str_starts_with($namespace, $prefix),
        ));
    }

    /**
     * Every class the project defines under app/, as fully qualified names
     * derived from the PSR-4 root. Reading the filesystem rather than the
     * autoloader's classmap keeps this honest about what is in the repository.
     *
     * Files that declare no class of their own name are skipped without being
     * loaded: a domain's routes.php sits inside the PSR-4 root, and autoloading
     * it would execute Route:: calls against a container that is not there.
     *
     * @return list<class-string>
     */
    public static function applicationClasses(): array
    {
        $root = dirname(__DIR__, 3).'/app';
        $classes = [];

        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || ! self::declaresClass($file)) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1, -strlen('.php'));
            $class = 'App\\'.str_replace('/', '\\', $relative);

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * Every PHP file the project defines under app/, as a path relative to it.
     *
     * Paths rather than class names, and every file rather than only those that
     * declare a class: a rule about what may appear in the source - an import,
     * a vendor namespace - has to be able to see a file the autoloader never
     * loads, and a relative path is what makes a failure readable.
     *
     * @return list<string>
     */
    public static function applicationFiles(): array
    {
        $root = dirname(__DIR__, 3).'/app';
        $paths = [];

        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $paths[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        sort($paths);

        return $paths;
    }

    private static function declaresClass(SplFileInfo $file): bool
    {
        $contents = (string) file_get_contents($file->getPathname());
        $expected = preg_quote($file->getBasename('.php'), '/');

        return preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*class\s+'.$expected.'\b/m', $contents) === 1;
    }

    private static function domainsDir(): string
    {
        return dirname(__DIR__, 3).'/app/Domains';
    }
}
