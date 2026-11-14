<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

/**
 * Mirrors Subscriber\CodeCoverage's pcov guard: pcov's collection scope is
 * PHP_INI_SYSTEM-only and excludes paths outside pcov.directory, so tests
 * exercising the subscriber must know whether this environment can trigger
 * the code path at all.
 */
final class PcovEnvironment
{
    /**
     * @param list<string> $paths paths the test would analyze
     *
     * @return bool|null whether pcov's collection scope covers the paths,
     *                    or null when the environment cannot exercise the
     *                    driver at all (pcov absent, unusable cwd)
     */
    public static function coversPaths(array $paths): ?bool
    {
        if (!\function_exists('\\pcov\\start')) {
            return null;
        }

        $cwd = \getcwd();

        if ($cwd === false) {
            return null;
        }

        $configured = \ini_get('pcov.directory');
        $directory = $configured === false || $configured === '' ? $cwd . '/src' : $configured;

        foreach ($paths as $path) {
            $realPath = \realpath($path);

            if ($realPath !== false && (\str_starts_with($realPath, $directory . '/') || $realPath === $directory)) {
                return true;
            }
        }

        return false;
    }

    public static function skipReason(): string
    {
        return \function_exists('\\pcov\\start')
            ? 'could not determine current working directory'
            : 'pcov is not loaded';
    }
}
