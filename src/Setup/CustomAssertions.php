<?php

declare(strict_types=1);

namespace TH\DocTest\Setup;

use TH\DocTest\Setup\CustomAssertions\Call;

/**
 * implemented by a {@see \TH\DocTest\Attributes\ExamplesSetup} setup class that
 * provides or overrides `self::assert*` calls made inside examples: every
 * registered handler is asked in registration order with the raw call
 * (`name` without the `assert` prefix, arguments) and a `$next` callable
 * continuing down the chain until the default dispatch (Webmozart Assert) —
 * call `$next()` to delegate (optionally with a different Call, e.g. to alias
 * one assertion to another), don't call it to override; throwing from a handler
 * fails the example like any failed assertion would
 *
 * ```php
 * final class CustomAssertions implements CustomAssertions
 * {
 *     public function assertion(CustomAssertions\Call $call, \Closure $next): void
 *     {
 *         if ($call->name === 'Result') {
 *             // self::assertResult($x, …) handled here, default dispatch bypassed
 *         }
 *
 *         $next(); // everything else falls through to the default dispatch
 *     }
 * }
 * ```
 */
interface CustomAssertions
{
    /**
     * @param \Closure(Call|null=): void $next
     */
    public function assertion(Call $call, \Closure $next): void;
}
