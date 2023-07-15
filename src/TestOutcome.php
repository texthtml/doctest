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

    public static function and(
        self $left,
        self $right,
    ): self {
        return $left->isFailure()
            ? $left
            : $right;
    }
}
