<?php

declare(strict_types=1);

namespace TH\DocTest\Mago;

/**
 * fails an example Mago reported issues on
 */
final class IssuesFound extends \RuntimeException
{
    /**
     * @param non-empty-list<Issue> $issues
     */
    public function __construct(
        public readonly array $issues,
    ) {
        parent::__construct(\implode(PHP_EOL, \array_map(\strval(...), $issues)));
    }
}
