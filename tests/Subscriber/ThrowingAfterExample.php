<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use TH\DocTest\Setup\AfterExample;

final class ThrowingAfterExample implements AfterExample
{
    /** @var list<string> */
    public static array $calls = [];

    public function __construct()
    {
        self::$calls[] = 'construct';
    }

    public function afterExample(): void
    {
        self::$calls[] = 'after';

        throw new \LogicException('cleanup must not mask the example outcome');
    }
}
