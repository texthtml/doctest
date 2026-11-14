<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use TH\DocTest\Setup\EndOfTest;

final class ThrowingEndOfTestSetup implements EndOfTest
{
    /** @var list<string> */
    public static array $calls = [];

    public function endOfTest(): void
    {
        self::$calls[] = 'endOfTest';

        throw new \LogicException('hook failure must not mask the example outcome');
    }
}
