<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\Location;

final class SourceComments implements Comments
{
    public function __construct(
        private CommentReflectionSources $commentReflectionSources,
    ) {}

    /**
     * @return \Traversable<Location,string>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->commentReflectionSources as $commentReflectionSource) {
            $comment = $commentReflectionSource->getDocComment();

            if ($comment !== false) {
                yield Location::fromReflection($commentReflectionSource, $comment) => $comment;
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
