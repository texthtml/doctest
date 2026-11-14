<?php

declare(strict_types=1);

namespace TH\DocTest\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS
| \Attribute::TARGET_METHOD
| \Attribute::TARGET_FUNCTION
| \Attribute::IS_REPEATABLE)]
final class ExamplesSetup
{
    public function __construct(
        /**
         * instantiated fresh before each example the attribute applies to, and
         * released after it
         *
         * @var object
         */
        public readonly object $setup,
    ) {}
}
