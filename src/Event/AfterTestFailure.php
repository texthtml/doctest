<?php declare(strict_types=1);

namespace TH\DocTest\Event;

use TH\DocTest\Example;

final class AfterTestFailure
{
    public function __construct(
        public readonly Example $example,
        public readonly \Throwable $failure,
    ) {
    }
}
