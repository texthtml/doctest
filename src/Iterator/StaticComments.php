<?php

declare(strict_types=1);

namespace TH\DocTest\Iterator;

use Psr\Log\LoggerInterface;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\TestCase;

/**
 * Doc comments harvested statically (no file included at scan time).
 */
final class StaticComments implements Comments
{
    public function __construct(
        private readonly StaticCommentSources $sources,
    ) {}

    /**
     * @return \Traversable<Comment|TestCase\SourceError>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->sources as $source) {
            $comment = $source->docComment;

            yield new Comment($comment, CodeLocation::fromSymbol($source, $comment));
        }
    }

    /**
     * @param array<string> $paths path to files and folders to look for PHP comments in
     */
    public static function fromPaths(array $paths, LoggerInterface $logger): self
    {
        return new self(new StaticCommentSources($paths, $logger));
    }
}
