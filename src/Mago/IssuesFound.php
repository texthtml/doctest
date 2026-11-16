<?php

declare(strict_types=1);

namespace TH\DocTest\Mago;

/**
 * fails an example Mago reported issues on
 */
final class IssuesFound extends \RuntimeException
{
    public function __construct(
        /** @var non-empty-list<Issue> */
        public readonly array $issues,
    ) {
        parent::__construct(\implode(PHP_EOL, \array_map(\strval(...), $issues)));
    }
}
