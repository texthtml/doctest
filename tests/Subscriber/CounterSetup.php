<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use TH\DocTest\SetUp;

final class CounterSetup implements SetUp
{
    /** @var list<string> */
    public static array $instantiations = [];

    public function __construct(
        public readonly string $key,
    ) {
        self::$instantiations[] = $key;
    }
}
