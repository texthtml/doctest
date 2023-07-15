<?php declare(strict_types=1);

namespace TH\DocTest\Event;

use TH\DocTest\TestOutcome;

final class AfterTestSuite
{
    public function __construct(
        public readonly TestOutcome $outcome,
    ) {
    }
}
