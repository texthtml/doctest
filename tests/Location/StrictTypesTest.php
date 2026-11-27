<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Location;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TH\DocTest\Location\StrictTypes;

final class StrictTypesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function files(): iterable
    {
        yield 'strict' => ["<?php\n\ndeclare(strict_types=1);\n\nnamespace Foo;\n", true];
        yield 'compact' => ['<?php declare(strict_types = 1); echo 1;', true];
        yield 'after a doc comment' => ["<?php\n/** file */\ndeclare(strict_types=1);\n", true];
        yield 'with other directives' => ["<?php\ndeclare(ticks=1, strict_types=1);\n", true];
        yield 'after another declare' => ["<?php\ndeclare(ticks=1);\ndeclare(strict_types=1);\n", true];
        yield 'after a shebang' => ["#!/usr/bin/env php\n<?php\ndeclare(strict_types=1);\n", true];
        yield 'coercive' => ["<?php\n\nnamespace Foo;\n", false];
        yield 'explicitly coercive' => ["<?php\ndeclare(strict_types=0);\n", false];
        yield 'mentioned later only' => ["<?php\necho 1;\n// declare(strict_types=1);\n", false];
    }

    #[DataProvider('files')]
    public function testDetectsStrictTypesDeclarations(string $code, bool $strict): void
    {
        $file = \tempnam(\sys_get_temp_dir(), prefix: 'doctest-strict-types-');
        static::assertIsString($file);

        try {
            \file_put_contents($file, $code);

            static::assertSame($strict, StrictTypes::declaredIn($file));
        } finally {
            \unlink($file);
        }
    }

    public function testAMissingFileIsNotStrict(): void
    {
        static::assertFalse(StrictTypes::declaredIn('/nonexistent/doctest/file.php'));
    }
}
