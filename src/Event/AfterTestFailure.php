<?php declare(strict_types=1);

namespace TH\DocTest\Event;

use TH\DocTest\TestCase;

final class AfterTestFailure
{
    public function __construct(
        public readonly TestCase $test,
        public readonly \Throwable $failure,
    ) {
    }
}
