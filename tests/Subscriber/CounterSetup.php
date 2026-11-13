<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

final class CounterSetup
{
    /** @var list<string> */
    public static array $instantiations = [];

    public function __construct(
        public readonly string $key,
    ) {
        self::$instantiations[] = $key;
    }
}
