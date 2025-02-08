<?php declare(strict_types=1);

namespace TH\DocTest\Attributes;

#[\Attribute]
final readonly class ExamplesSetup
{
    public function __construct(
        /** @var class-string */
        public string $setupClass,
    ) {
    }
}
