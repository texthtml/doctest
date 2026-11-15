<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Iterator;

use PHPUnit\Framework\TestCase;
use TH\DocTest\Iterator\AllTests;
use TH\DocTest\Iterator\StaticCommentSources;

final class StaticScanTest extends TestCase
{
    public function testFindsExamplesWithoutIncludingAnyFile(): void
    {
        $tests = \iterator_to_array(AllTests::from(
            new \TH\DocTest\Iterator\StaticComments(new StaticCommentSources([self::fixturePath()], self::logger())),
            ['php'],
        ));

        static::assertNotEmpty($tests, 'the fixture example must be discovered');
        static::assertNotContains(
            Fixtures\StaticScanFixture::class,
            \get_declared_classes(),
            'static scanning must not execute (and thus declare) the code under test',
        );
    }

    public function testDiscoveredExampleExecutesCorrectly(): void
    {
        // LazyLoadedTests (wired by TestSuite::fromPaths) is what includes the
        // fixture file before its examples run; drive the same pipeline
        $testSuite = \TH\DocTest\TestSuite::fromPaths(
            [self::fixturePath()],
            '',
            ['php'],
            self::logger(),
            staticScan: true,
        );

        $outcome = $testSuite->run(bail: false);

        static::assertTrue($outcome->isSuccess(), 'the fixture example must pass through the static pipeline');
        static::assertTrue(
            \class_exists(Fixtures\StaticScanFixture::class),
            'running the example must have lazily included the fixture file',
        );
    }

    public function testSymbolsCarryTheirNamespaceAndSetups(): void
    {
        $symbolCount = 0;
        $functionSymbol = null;

        foreach (new StaticCommentSources([__DIR__ . '/fixtures/static-scan-setup.php'], self::logger()) as $symbol) {
            $symbolCount++;
            $functionSymbol = $symbol;
        }

        static::assertSame(1, $symbolCount);
        static::assertNotNull($functionSymbol);
        static::assertSame('function', $functionSymbol->kind);
        static::assertSame('TH\DocTest\Tests\Iterator\scanned_function', $functionSymbol->name);
        static::assertStringContainsString('scanned_function() === 42', $functionSymbol->docComment);
        static::assertCount(1, $functionSymbol->setups);
        static::assertStringContainsString(
            'new \TH\DocTest\Attributes\ExamplesSetup(new \TH\DocTest\Tests\Iterator\ScannedSetup())',
            $functionSymbol->setups[0],
        );
    }

    public function testMethodSymbolsMergeTheEnclosingClassSetupsFirst(): void
    {
        $methods = [];

        foreach (new StaticCommentSources(
            [__DIR__ . '/fixtures/ClassLevelSetupsFixture.php'],
            self::logger(),
        ) as $symbol) {
            if ($symbol->kind === 'method') {
                $methods[$symbol->name] = $symbol->setups;
            }
        }

        static::assertCount(2, $methods, 'both methods must be discovered');

        // class-level setups come first, then the method's own — mirroring
        // the reflection backend's `array_merge(class, method)`
        $greet = $methods['TH\DocTest\Tests\Iterator\Fixtures\ClassLevelSetupsFixture::greet(…)'];
        static::assertCount(2, $greet);
        static::assertSame(
            'new \TH\DocTest\Attributes\ExamplesSetup(new \TH\DocTest\Tests\Iterator\Fixtures\ClassLevelSetup())',
            $greet[0],
        );
        static::assertSame(
            'new \TH\DocTest\Attributes\ExamplesSetup(new \TH\DocTest\Tests\Iterator\Fixtures\MethodLevelSetup())',
            $greet[1],
        );

        // a method without its own attribute still gets the class-level setup
        static::assertSame(
            [$greet[0]],
            $methods['TH\DocTest\Tests\Iterator\Fixtures\ClassLevelSetupsFixture::wave(…)'],
        );
    }

    public function testDiscoveredExampleRunsClassAndMethodSetups(): void
    {
        $testSuite = \TH\DocTest\TestSuite::fromPaths(
            [__DIR__ . '/fixtures/ClassLevelSetupsFixture.php'],
            '',
            ['php'],
            self::logger(),
            staticScan: true,
        );

        $outcome = $testSuite->run(bail: false);

        static::assertTrue($outcome->isSuccess(), 'the class-level setup must apply to method examples');
    }

    private static function fixturePath(): string
    {
        return __DIR__ . '/fixtures/StaticScanFixture.php';
    }

    private static function logger(): \Psr\Log\NullLogger
    {
        return new \Psr\Log\NullLogger();
    }
}
