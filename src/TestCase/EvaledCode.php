<?php

declare(strict_types=1);

namespace TH\DocTest\TestCase;

use TH\DocTest\Setup\EndOfTestHooks;

/**
 * assembles the code actually eval'd for an example: the raw code, wrapped in a
 * try/finally whose finally carries the {@see EndOfTest} hooks when some are
 * registered — they run inside the example's scope (while its variables are
 * alive), even when the example ends abruptly (`return`, or an uncaught
 * exception, which then still propagates)
 *
 * the example's line numbers are preserved, so ParseError messages stay
 * faithful: `use` statements must leave the wrapper body (a `use` is only valid
 * in the outermost scope), so they are inlined right before the wrapper opens
 * and the lines they vacated become empty lines inside the try
 *
 * @internal
 */
final class EvaledCode
{
    public function __construct(
        private readonly string $code,
    ) {}

    public function __toString(): string
    {
        if (EndOfTestHooks::$hooks === []) {
            return $this->code;
        }

        $uses = [];
        $body = [];

        foreach (\explode(PHP_EOL, $this->code) as $line) {
            if (\preg_match('/^\s*use\s+.*;\s*$/', $line) === 1) {
                $uses[] = \trim($line);
            } else {
                $body[] = $line;
            }
        }

        // `try {` is glued to the first body line so the example's line numbers
        // are preserved (ParseError messages stay faithful); the hoisted `use`
        // statements keep their original line slots
        $first = \array_shift($body) ?? '';

        $hookCall = '\\' . EndOfTestHooks::class . '::invoke();';

        return (
            ($uses === [] ? '' : \implode(PHP_EOL, $uses) . PHP_EOL)
            . 'try {'
            . $first
            . ($body === [] ? '' : PHP_EOL . \implode(PHP_EOL, $body))
            . PHP_EOL
            . '} finally {'
            . PHP_EOL
            . "    {$hookCall}"
            . PHP_EOL
            . '}'
        );
    }
}
