<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use TH\DocTest\Setup\EndOfTest;

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
