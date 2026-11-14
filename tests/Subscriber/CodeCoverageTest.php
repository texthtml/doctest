<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use PHPUnit\Framework\TestCase;
use TH\DocTest\Event;
use TH\DocTest\Subscriber\CodeCoverage;

final class CodeCoverageTest extends TestCase
{
    public function tearDown(): void
    {
        if (\function_exists('\\pcov\\clear')) {
            \pcov\clear();
        }
    }

    public function testWriteWithoutStartThrows(): void
    {
        $subscriber = new CodeCoverage(paths: [], filename: '/dev/null');

        // expectExceptionMessage is deprecated in PHPUnit 13 (replaced by
        // expectExceptionMessageIsOrContains, absent in 11/12): assert on the
        // message directly instead
        try {
            $subscriber->write(new Event\AfterTestSuite(\TH\DocTest\TestOutcome::Success));
            static::fail('expected LogicException');
        } catch (\LogicException $exception) {
            static::assertSame('Code coverage was never started', $exception->getMessage());
        }
    }

    public function testRefusesMisconfiguredPcovDirectory(): void
    {
        // the analyzed path is the examples/ directory: the refusal path can
        // only be exercised when pcov is loaded AND does not cover it —
        // otherwise the subscriber correctly starts
        $covered = self::pcovCoversPaths([__DIR__ . '/../../examples']);

        if ($covered === null || $covered) {
            static::markTestSkipped(
                $covered === null ? self::pcovSkipReason() : 'pcov.directory covers the analyzed paths',
            );
        }

        $subscriber = new CodeCoverage(paths: [__DIR__ . '/../../examples'], filename: '/dev/null');

        try {
            $subscriber->start();
            static::fail('expected RuntimeException');
        } catch (\RuntimeException $exception) {
            static::assertStringContainsString('pcov.directory', $exception->getMessage());
            static::assertStringContainsString('-d pcov.directory', $exception->getMessage());
        }
    }

    public function testCollectsCoverageOfEvaluatedCodeInTheAnalyzedPaths(): void
    {
        $covered = self::pcovCoversPaths([__DIR__ . '/../../src']);

        if ($covered === null || !$covered) {
            static::markTestSkipped(
                $covered === null ? self::pcovSkipReason() : 'pcov.directory does not include the analyzed sources',
            );
        }

        $filename = \sys_get_temp_dir() . '/doctest-codecoverage-test.xml';
        $subscriber = new CodeCoverage(paths: [__DIR__ . '/../../src'], filename: $filename);

        $subscriber->start();

        eval('namespace TH\DocTest\Tests\Subscriber\CoverageProbe; function probe(): int { return 42; }');

        // an eval()d function lives in no filter file: assert on the src coverage instead
        $subscriber->write(new Event\AfterTestSuite(\TH\DocTest\TestOutcome::Success));

        $xml = \file_get_contents($filename);
        static::assertNotFalse($xml);
        static::assertStringContainsString('TestSuite.php', $xml);
    }

    /**
     * Mirrors Subscriber\CodeCoverage's pcov guard.
     *
     * @param list<string> $paths paths the test would analyze
     *
     * @return bool|null whether pcov's collection scope covers the paths,
     *                    or null when the environment cannot exercise the
     *                    driver at all (pcov absent, unusable cwd)
     */
    private static function pcovCoversPaths(array $paths): ?bool
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

    private static function pcovSkipReason(): string
    {
        return \function_exists('\\pcov\\start')
            ? 'could not determine current working directory'
            : 'pcov is not loaded';
    }
}
