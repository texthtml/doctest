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
            $x = $call->arguments[0] ?? null;
            $y = $call->arguments[1] ?? null;

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

        if ($call->name === 'Zero') {
            // rewrite: alias `assertZero($x)` to `Same(0, $x)` for the rest of the chain
            /** @var array<int, string> $args */
            $args = $call->arguments;
            $rewritten = new Call('Same', [0, $args[0] ?? null]);
            self::$calls[] = 'rewrite: Zero → Same(0, ' . implode(', ', \array_map(strval(...), $args)) . ')';
            $next($rewritten);

            return;
        }

        // register/fall-through: everything else continues down the chain
        $next();
    }
}
