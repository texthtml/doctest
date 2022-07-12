<?php declare(strict_types=1);

namespace TH\DocTest\Event;

final class AfterTestSuite
{
    public function __construct(
        public readonly bool $success,
    ) {
    }
}
