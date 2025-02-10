<?php declare(strict_types=1);

namespace TH\DocTest\Event;

use TH\DocTest\TestCase;

final class AfterTestSuccess
{
    public function __construct(
        public readonly TestCase $test,
    ) {
    }
}
