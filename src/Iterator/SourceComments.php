<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\Location\CodeLocation;
use TH\DocTest\TestCase;

final class SourceComments implements Comments
{
    public function __construct(
        private CommentReflectionSources $commentReflectionSources,
    ) {}

    /**
     * @return \Traversable<Comment|TestCase\SourceError>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->commentReflectionSources as $commentReflectionSource) {
            if ($commentReflectionSource instanceof TestCase\SourceError) {
                yield $commentReflectionSource->location() => $commentReflectionSource;

                continue;
            }

            $comment = $commentReflectionSource->getDocComment();

            if ($comment !== false) {
                yield new Comment(
                    $comment,
                    CodeLocation::fromReflection($commentReflectionSource, $comment),
                );
            }
        }
    }

    /**
     * @param array<string> $paths path to files and folders to look for PHP comments in
     */
    public static function fromPaths(array $paths): self
    {
        return new self(new CommentReflectionSources($paths));
    }
}
