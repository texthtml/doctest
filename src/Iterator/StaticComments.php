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
            $endLine = $source->docEndLine ?? 1;

            yield new Comment($comment, CodeLocation::fromSymbol(
                $source,
                $comment,
                self::relative($source->path),
                $endLine,
            ));
        }
    }

    /**
     * @param array<string> $paths path to files and folders to look for PHP comments in
     */
    public static function fromPaths(array $paths, LoggerInterface $logger): self
    {
        return new self(new StaticCommentSources($paths, $logger));
    }

    private static function relative(string $path): string
    {
        $cwd = \getcwd();

        if ($cwd === false) {
            return $path;
        }

        return (
            \preg_replace(
                pattern: '/^' . \preg_quote(str: $cwd, delimiter: '/') . '\/?/',
                replacement: '',
                subject: $path,
            ) ?? $path
        );
    }
}
