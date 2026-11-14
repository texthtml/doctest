<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use TH\DocTest\Setup\AfterExample;
use TH\DocTest\Setup\BeforeExample;

final class HookedSetup implements BeforeExample, AfterExample
{
    /** @var list<string> */
    public static array $calls = [];

    public function __construct(
        public readonly string $key = '',
    ) {
        self::$calls[] = "construct {$this->key}";
    }

    public function beforeExample(): void
    {
        self::$calls[] = "before {$this->key}";
    }

    public function afterExample(): void
    {
        self::$calls[] = "after {$this->key}";
    }
}

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
