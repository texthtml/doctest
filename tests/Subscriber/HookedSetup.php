<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use TH\DocTest\Setup\AfterExample;
use TH\DocTest\Setup\BeforeExample;
use TH\DocTest\Setup\EndOfTest;

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

final class EndOfTestSetup implements EndOfTest
{
    /** @var list<string> */
    public static array $observations = [];

    /** set by the example code itself, lets the hook probe the example's locals */
    public static ?\WeakReference $probe = null;

    public function endOfTest(): void
    {
        self::$observations[] =
            self::$probe !== null && self::$probe->get() !== null
                ? 'example variable still alive'
                : 'example variable gone';
    }
}
