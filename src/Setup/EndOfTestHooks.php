<?php

declare(strict_types=1);

namespace TH\DocTest\Setup;

/**
 * routes the eval'd example code to the current run's {@see EndOfTest} hooks:
 * examples end with `EndOfTestHooks::invoke(i)` calls so the hooks run inside
 * the example's scope, while its variables are still alive. Library internals —
 * not user code — should call this
 *
 * @internal
 */
final class EndOfTestHooks
{
    /** @var list<callable(): void> */
    public static array $hooks = [];

    public static function invoke(int $index): void
    {
        self::$hooks[$index]();
    }
}
