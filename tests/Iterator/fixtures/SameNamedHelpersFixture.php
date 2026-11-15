<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Iterator\Fixtures;

abstract class SameNamedHelpersFixture
{
    /**
     * # Examples
     *
     * ```php
     * function helper(): int { return 1; }
     * assert(helper() === 1);
     * ```
     */
    abstract public function one(): int;

    /**
     * # Examples
     *
     * ```php
     * function helper(): int { return 2; }
     * assert(helper() === 2);
     * ```
     */
    abstract public function two(): int;
}
