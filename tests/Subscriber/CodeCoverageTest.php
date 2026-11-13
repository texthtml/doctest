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
        if (!\function_exists('\\pcov\\start')) {
            static::markTestSkipped('pcov is not loaded');
        }

        $cwd = \getcwd();

        if ($cwd === false) {
            static::markTestSkipped('could not determine current working directory');
        }

        $configured = \ini_get('pcov.directory');
        $directory = $configured === false || $configured === '' ? $cwd . '/src' : $configured;

        // only meaningful when the environment *would* silently report 0%:
        // a pcov.directory that excludes the analyzed paths (here: vendor/,
        // which stands in for "somewhere the project under test lives")
        $vendor = \realpath($cwd . '/vendor');

        if ($vendor === false || \str_starts_with($vendor, $directory)) {
            static::markTestSkipped("pcov.directory ({$directory}) covers the analyzed paths");
        }

        $subscriber = new CodeCoverage(paths: [$vendor], filename: '/dev/null');

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
        if (!\function_exists('\\pcov\\start')) {
            static::markTestSkipped('pcov is not loaded');
        }

        $cwd = \getcwd();

        if ($cwd === false) {
            static::markTestSkipped('could not determine current working directory');
        }

        $configured = \ini_get('pcov.directory');
        $directory = $configured === false || $configured === '' ? $cwd . '/src' : $configured;
        $src = \realpath(__DIR__ . '/../../src');

        if ($src === false || !\str_starts_with($src, $directory)) {
            static::markTestSkipped('pcov.directory (' . $directory . ') does not include the analyzed sources');
        }

        $filename = \sys_get_temp_dir() . '/doctest-codecoverage-test.xml';
        $subscriber = new CodeCoverage(paths: [$src], filename: $filename);

        $subscriber->start();

        eval('namespace TH\DocTest\Tests\Subscriber\CoverageProbe; function probe(): int { return 42; }');

        // an eval()d function lives in no filter file: assert on the src coverage instead
        $subscriber->write(new Event\AfterTestSuite(\TH\DocTest\TestOutcome::Success));

        $xml = \file_get_contents($filename);

        if ($xml === false) {
            throw new \RuntimeException("could not read {$filename}");
        }

        static::assertStringContainsString('TestSuite.php', $xml);
    }
}
