<?php

declare(strict_types=1);

namespace TH\DocTest\Attributes;

use TH\DocTest\SetUp;

#[\Attribute(\Attribute::TARGET_CLASS
| \Attribute::TARGET_METHOD
| \Attribute::TARGET_FUNCTION
| \Attribute::IS_REPEATABLE)]
final class ExamplesSetup
{
    /**
     * @param SetUp $setup instantiated fresh before each example the attribute applies to
     */
    public function __construct(
        public readonly SetUp $setup,
    ) {}
}
