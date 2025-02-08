<?php declare(strict_types=1);

namespace TH\DocTest\Event;

use TH\DocTest\TestOutcome;

final readonly class AfterTestSuite
{
    public function __construct(
        public TestOutcome $outcome,
    ) {
    }
}
