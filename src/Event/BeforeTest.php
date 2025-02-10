<?php declare(strict_types=1);

namespace TH\DocTest\Event;

use TH\DocTest\TestCase;

final class BeforeTest
{
    public function __construct(
        public readonly TestCase $test,
    ) {
    }
}
