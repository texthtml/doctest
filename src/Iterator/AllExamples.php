<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\Example;
use TH\DocTest\Location;

final class AllExamples implements Examples
{
    public function __construct(
        private Comments $comments,
    ) {
    }

    /**
     * @return \Traversable<Example>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->comments as $location => $comment) {
            yield from $this->iterateComment($comment, $location);
        }
    }

    /**
     * @param array<string> $paths paths to files and folder to look for PHP comments code examples in
     */
    public static function fromPaths(array $paths): self
    {
        return new self(Comments::fromPaths($paths));
    }

    /**
     * @return \Traversable<Example>
     */
    private function iterateComment(string $comment, Location $location): \Traversable
    {
        $lines = new \ArrayIterator(\explode(PHP_EOL, $comment));
        $index = 1;

        while ($example = $this->nextExample($lines, $location, $index++)) {
            yield $example;
        }
    }

    /**
     * @param \ArrayIterator<int,string> $lines
     */
    private function nextExample(\ArrayIterator $lines, Location $location, int $index): ?Example
    {
        $codeblockStartedAt = $this->findFencedCodeBlockStart($lines);

        if ($codeblockStartedAt === null) {
            return null;
        }

        return $this->readExample($lines, $location->startingAt($codeblockStartedAt, $index));
    }

    /**
     * @param \ArrayIterator<int,string> $lines
     */
    private function readExample(\ArrayIterator $lines, Location $location): ?Example
    {
        $buffer = [];

        while ($lines->valid()) {
            $line = $lines->current();
            $lines->next();

            if ($this->endOfAPHPFencedCodeBlock($line)) {
                return new Example(\implode(PHP_EOL, $buffer), $location->ofLength($lines->key()));
            }

            $buffer[] = \preg_replace("/^\s*\*( ?)/", "", $line);
        }

        return null;
    }

    /**
     * @param \ArrayIterator<int,string> $lines
     */
    private function findFencedCodeBlockStart(\ArrayIterator $lines): ?int
    {
        while ($lines->valid()) {
            $line = $lines->current();
            $lines->next();

            if ($this->startOfAPHPFencedCodeBlock($line)) {
                return $lines->key();
            }
        }

        return null;
    }

    private function endOfAPHPFencedCodeBlock(string $line): bool
    {
        return \ltrim($line) === "* ```";
    }

    private function startOfAPHPFencedCodeBlock(string $line): bool
    {
        return \in_array(\ltrim($line), ["* ```", "* ```php"], strict: true);
    }
}
