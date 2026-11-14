<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Iterator\Fixtures;

final class StaticScanFixture
{
    /**
     * ```php
     * $answer = 6 * 7;
     * ```
     */
    public static function answer(): int
    {
        return 42;
    }
}

StaticScanFixture::answer();
