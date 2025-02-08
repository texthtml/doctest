<?php declare(strict_types=1);

namespace TH\DocTest\TestCase;

final readonly class ExpectedFailure
{
    public function __construct(
        /** @var class-string<\Throwable> */
        public string $class,
        public string $message,
    ) {}
}
