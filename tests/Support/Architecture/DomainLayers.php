<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DomainLayers
{
    /** @var list<class-string>|null */
    private static ?array $applicationClasses = null;

    /** @var list<string>|null */
    private static ?array $applicationFiles = null;

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
     * @return list<class-string>
     */
    public static function applicationClasses(): array
    {
        if (self::$applicationClasses !== null) {
            return self::$applicationClasses;
        }

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

        return self::$applicationClasses = $classes;
    }

    /**
     * @return list<string>
     */
    public static function applicationFiles(): array
    {
        if (self::$applicationFiles !== null) {
            return self::$applicationFiles;
        }

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

        return self::$applicationFiles = $paths;
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
