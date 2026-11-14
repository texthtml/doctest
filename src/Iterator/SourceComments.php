<?php

declare(strict_types=1);

namespace TH\DocTest\Iterator;

use Psr\Log\LoggerInterface;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\Location\SymbolSource;
use TH\DocTest\TestCase;

final class SourceComments implements Comments
{
    public function __construct(
        private CommentReflectionSources|StaticCommentSources $sources,
    ) {}

    /**
     * @return \Traversable<Comment|TestCase\SourceError>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->sources as $source) {
            if ($source instanceof TestCase\SourceError) {
                yield $source->location() => $source;

                continue;
            }

            $comment = $source instanceof SymbolSource ? $source->docComment : $source->getDocComment();

            if ($comment === false) {
                continue;
            }

            // reflection sources are `ReflectionClass<object>|…`; the differing generic parameters
            // are equivalent at runtime for the metadata extraction done here
            // @mago-expect analysis:less-specific-nested-argument-type
            yield new Comment(
                $comment,
                $source instanceof SymbolSource
                    ? CodeLocation::fromSymbol($source, $comment)
                    : CodeLocation::fromReflection($source, $comment),
            );
        }
    }

    /**
     * @param array<string> $paths path to files and folders to look for PHP comments in
     */
    public static function fromPaths(array $paths, LoggerInterface $logger): self
    {
        return new self(new CommentReflectionSources($paths, $logger));
    }
}
