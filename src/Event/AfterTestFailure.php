<?php declare(strict_types=1);

namespace TH\DocTest\Event;

use TH\DocTest\TestCase;

final readonly class AfterTestFailure
{
    public function __construct(
        public TestCase $test,
        public \Throwable $failure,
    ) {
    }
}
