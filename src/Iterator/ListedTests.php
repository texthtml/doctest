<?php

declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\TestCase;

/**
 * tests already discovered
 */
final class ListedTests implements Tests
{
    public function __construct(
        /** @var list<TestCase> */
        private readonly array $tests,
    ) {}

    /**
     * @return \Traversable<TestCase>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->tests;
    }
}
