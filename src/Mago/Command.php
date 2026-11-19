<?php

declare(strict_types=1);

namespace TH\DocTest\Mago;

/**
 * the mago commands examples can be checked with
 */
enum Command: string
{
    case Lint = 'lint';
    case Analyze = 'analyze';

    /**
     * the `mago.toml` section configuring the command
     */
    public function section(): string
    {
        return match ($this) {
            self::Lint => 'linter',
            self::Analyze => 'analyzer',
        };
    }

    /**
     * what the command runs on: the linter only needs the examples, the
     * analyzer also needs their context (see {@see Runner})
     */
    public function path(ExportDirectory $directory): string
    {
        return match ($this) {
            self::Lint => "{$directory->name}/examples",
            self::Analyze => $directory->name,
        };
    }
}
