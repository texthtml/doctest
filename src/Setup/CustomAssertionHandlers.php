<?php

declare(strict_types=1);

namespace TH\DocTest\Setup;

/**
 * routes the eval'd example code's `self::assert*` calls to the current run's
 * {@see CustomAssertions} handlers: each registered handler is asked in
 * registration order, and a `$next` callable continues down the chain until the
 * default dispatch (Webmozart Assert). Exceptions thrown by a handler or the
 * default dispatch propagate untouched — an assertion failing is exactly how an
 * example fails. Library internals — not user code — should call this
 *
 * @internal
 */
final class CustomAssertionHandlers
{
    /** @var list<CustomAssertions> */
    public static array $handlers = [];

    public static function invoke(string $name, array $arguments): void
    {
        // `assertEq` → `eq`, `assertStringNotEmpty` → `stringNotEmpty`: the
        // call name is normalized to Webmozart's camelCase so handlers compare
        // against real method names; PHP resolves the default dispatch
        // case-insensitively either way
        self::dispatch(0, new CustomAssertions\Call(\lcfirst($name), $arguments));
    }

    private static function dispatch(int $index, CustomAssertions\Call $call): void
    {
        if ($index === \count(self::$handlers)) {
            self::defaultDispatch($call);

            return;
        }

        /**
         * $next() delegates the call as-is; $next($override) replaces it for the
         * rest of the chain (aliasing one assertion to another, tweaking args…)
         *
         * @param CustomAssertions\Call|null $override
         */
        $next = static function (?CustomAssertions\Call $override = null) use ($index, $call): void {
            self::dispatch($index + 1, $override ?? $call);
        };

        self::$handlers[$index]->assertion($call, $next);
    }

    private static function defaultDispatch(CustomAssertions\Call $call): void
    {
        // proxying Webmozart Assert calls made from evaluated examples: the method name is dynamic by design
        // an unknown name naturally raises `Error: Call to undefined method`, like before the chain existed
        \Webmozart\Assert\Assert::{$call->name}(...$call->arguments);
    }
}
