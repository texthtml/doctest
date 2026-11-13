<?php

declare(strict_types=1);

namespace TH\DocTest\Attributes;

use TH\DocTest\SetUp;

/**
 * @phpstan-type Target \ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction
 */
#[\Attribute(\Attribute::TARGET_CLASS
| \Attribute::TARGET_METHOD
| \Attribute::TARGET_FUNCTION
| \Attribute::IS_REPEATABLE)]
final class ExamplesSetup
{
    /**
     * @param SetUp|non-empty-list<SetUp> $setups run in order before each example, freshly
     *                                           instantiated each time the attribute is reflected
     */
    public function __construct(
        public readonly array|SetUp $setups,
    ) {}
}
