<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\Location\SymbolSource;
use TH\DocTest\TestCase\Example;

/**
 * eval'd code doesn't inherit `strict_types`: examples get the documented
 * file's mode from their header
 */
final class StrictTypesExampleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function files(): iterable
    {
        yield 'strict file' => ["<?php\ndeclare(strict_types=1);\n", true];
        yield 'coercive file' => ["<?php\n", false];
    }

    #[DataProvider('files')]
    public function testExamplesRunInTheModeOfTheirFile(string $code, bool $strict): void
    {
        $file = \tempnam(\sys_get_temp_dir(), prefix: 'doctest-strict-types-');
        static::assertIsString($file);

        try {
            \file_put_contents($file, $code);

            $source = new SymbolSource('function', 'doc', 'doc', '', '', [], $file, 3);
            $example = new Example('\strlen(1);', new CodeLocation($source, 'doc', $file, 1, 1, 1));

            static::assertSame($strict, \str_starts_with($example->header(), 'declare(strict_types=1);'));

            if ($strict) {
                $this->expectException(\TypeError::class);
            }

            $example->eval();
        } finally {
            \unlink($file);
        }
    }
}
