<?php declare(strict_types=1);

namespace TH\DocTest\Event;

use TH\DocTest\TestCase;

final readonly class ExecuteTest
{
    public function __construct(
        public TestCase $test,
    ) {
    }
}
