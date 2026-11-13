<?php

declare(strict_types=1);

namespace TH\DocTest\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS
| \Attribute::TARGET_METHOD
| \Attribute::TARGET_FUNCTION
| \Attribute::IS_REPEATABLE)]
final class ExamplesSetup
{
    /**
     * @param object $setup instantiated fresh before each example the attribute applies
     *                      to, and released after it
     */
    public function __construct(
        public readonly object $setup,
    ) {}
}
