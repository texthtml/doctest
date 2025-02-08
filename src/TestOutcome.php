<?php declare(strict_types=1);

namespace TH\DocTest;

enum TestOutcome
{
    case Success;
    case Failure;

    public function isSuccess(): bool
    {
        return $this === self::Success;
    }

    public function isFailure(): bool
    {
        return $this === self::Failure;
    }

    public function and(self $right): self
    {
        return $this === self::Success
            ? $right
            : $this;
    }
}
