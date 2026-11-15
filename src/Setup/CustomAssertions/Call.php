<?php

declare(strict_types=1);

namespace TH\DocTest\Setup\CustomAssertions;

/**
 * a `self::assert*` call made inside an example, routed through the
 * {@see \TH\DocTest\Setup\CustomAssertions} chain before the default dispatch
 */
final class Call
{
    public function __construct(
        /**
         * asserted name, without the `assert` prefix and with the first letter
         * lowercased: `assertEq(…)` → `eq`, `assertTrue(…)` → `true`,
         * `assertResultUsed(…)` → `resultUsed`
         */
        public readonly string $name,
        /** @var array<mixed> */
        public readonly array $arguments,
    ) {}
}
