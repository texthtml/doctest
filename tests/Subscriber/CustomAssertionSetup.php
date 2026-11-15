<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use TH\DocTest\Setup\CustomAssertions;
use TH\DocTest\Setup\CustomAssertions\Call;

final class CustomAssertionSetup implements CustomAssertions
{
    /** @var list<string> */
    public static array $calls = [];

    public function assertion(Call $call, \Closure $next): void
    {
        self::$calls[] = "handler: {$call->name}";

        if ($call->name === 'Result') {
            // override: `self::assertResult(x, y)` asserts identity, default dispatch bypassed
            $x = $call->arguments[0];
            $y = $call->arguments[1];

            if ($x !== $y) {
                throw new \LogicException('assertResult failed');
            }

            return;
        }

        if ($call->name === 'True') {
            // wrap: record, delegate to the default dispatch, record
            $next();
            self::$calls[] = 'handler: post-default';

            return;
        }

        // register/fall-through: everything else continues down the chain
        $next();
    }
}
