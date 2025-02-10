<?php declare(strict_types=1);

namespace TH\DocTest\Attributes;

#[\Attribute]
final class ExamplesSetup
{
    public function __construct(
        /** @var class-string */
        public readonly string $setupClass,
    ) {
    }
}
