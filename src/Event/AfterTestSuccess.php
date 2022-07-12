<?php declare(strict_types=1);

namespace TH\DocTest\Event;

use TH\DocTest\Example;

final class AfterTestSuccess
{
    public function __construct(
        public readonly Example $example,
    ) {
    }
}
