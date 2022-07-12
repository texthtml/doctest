<?php declare(strict_types=1);

namespace TH\DocTest\Event;

use TH\DocTest\Example;

final class AfterTest
{
    public function __construct(
        public readonly Example $example,
    ) {
    }
}
