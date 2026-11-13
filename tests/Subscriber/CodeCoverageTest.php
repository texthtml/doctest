<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use PHPUnit\Framework\TestCase;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Exception as CodeCoverageException;
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

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Code coverage was never started');
        $subscriber->write(new Event\AfterTestSuite(\TH\DocTest\TestOutcome::Success));
    }

    public function testCollectsCoverageOfEvaluatedCodeInTheAnalyzedPaths(): void
    {
        if (!\function_exists('\\pcov\\start')) {
            self::markTestSkipped('pcov is not loaded');
        }

        $cwd = \getcwd() ?: throw new \RuntimeException('could not determine current working directory');
        $directory = \ini_get('pcov.directory') ?: $cwd . '/src';
        $src = \realpath(__DIR__ . '/../../src');

        if ($src === false || !\str_starts_with($src, $directory)) {
            self::markTestSkipped("pcov.directory ({$directory}) does not include the analyzed sources");
        }

        $filename = \sys_get_temp_dir() . '/doctest-codecoverage-test.xml';
        $subscriber = new CodeCoverage(paths: [$src], filename: $filename);

        $subscriber->start();

        eval('namespace TH\DocTest\Tests\Subscriber\CoverageProbe; function probe(): int { return 42; }');

        // an eval()d function lives in no filter file: assert on the src coverage instead
        $subscriber->write(new Event\AfterTestSuite(\TH\DocTest\TestOutcome::Success));

        $xml = \file_get_contents($filename) ?: throw new \RuntimeException("could not read {$filename}");

        self::assertStringContainsString('TestSuite.php', $xml);
    }
}
