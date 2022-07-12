<?php declare(strict_types=1);

namespace TH\DocTest;

final class Example
{
    public function __construct(
        public readonly string $code,
        public readonly Location $location,
    ) {
    }

    public function eval(): void
    {
        eval($this->code);
    }
}
