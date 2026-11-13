<?php

declare(strict_types=1);

namespace TH\DocTest\Attributes;

use TH\DocTest\SetUp;

#[\Attribute(\Attribute::IS_REPEATABLE)]
final class ExamplesSetup
{
    /**
     * @param class-string<SetUp> $setupClass
     */
    public function __construct(
        public readonly string $setupClass,
    ) {}
}
