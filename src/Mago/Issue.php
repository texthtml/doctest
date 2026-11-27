<?php

declare(strict_types=1);

namespace TH\DocTest\Mago;

use TH\DocTest\Location\CodeLocation;

/**
 * a Mago issue reported on an example
 *
 * @internal
 */
final class Issue
{
    public function __construct(
        /** "error", "warning", "help" or "note" */
        public readonly string $level,
        /** the rule / analyzer code, e.g. `invalid-argument` */
        public readonly string $code,
        public readonly string $message,
        public readonly CodeLocation $example,
        /** 1-based line in the example */
        public readonly int $line,
    ) {}

    /**
     * where the issue is in the documented file
     */
    public function location(): string
    {
        if ($this->example->startLine === null) {
            return "{$this->example->path} ({$this->example->name}#{$this->example->index}, line {$this->line})";
        }

        return $this->example->path . ':' . ($this->example->startLine + $this->line - 1);
    }

    public function __toString(): string
    {
        return "{$this->location()}: {$this->level}[{$this->code}] {$this->message}";
    }
}
