<?php declare(strict_types=1);

namespace TH\DocTest\TestCase;

final class ExpectedFailure
{
    public function __construct(
        /** @var class-string<\Throwable> */
        public readonly string $class,
        public readonly string $message,
    ) {}
}
