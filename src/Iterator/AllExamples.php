<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\Example;
use TH\DocTest\Location;

final class AllExamples implements Examples
{
    /**
     * @param list<string>|null $acceptedLanguages Use empty string for unspecified language, and null for any languages
     */
    public function __construct(
        private readonly Comments $comments,
        private readonly ?array $acceptedLanguages,
    ) {}

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
     * @param list<string>|null $acceptedLanguages Use empty string for unspecified language, and null for any languages
     */
    public static function fromPaths(
        array $paths,
        ?array $acceptedLanguages,
    ): self {
        return new self(
            SourceComments::fromPaths($paths),
            $acceptedLanguages,
        );
    }

    /**
     * @return \Traversable<Example>
     */
    private function iterateComment(
        string $comment,
        Location $location,
    ): \Traversable {
        $lines = new \ArrayIterator(\explode(PHP_EOL, $comment));
        $index = 1;

        while ($example = $this->nextExample($lines, $location, $index++)) {
            yield $example;
        }
    }

    /**
     * @param \ArrayIterator<int,string> $lines
     */
    private function nextExample(
        \ArrayIterator $lines,
        Location $location,
        int $index,
    ): ?Example {
        $codeblockStartedAt = $this->findFencedPHPCodeBlockStart($lines);

        if ($codeblockStartedAt === null) {
            return null;
        }

        return $this->readExample(
            $lines,
            $location->startingAt($codeblockStartedAt, $index),
        );
    }

    /**
     * @param \ArrayIterator<int,string> $lines
     */
    private function readExample(
        \ArrayIterator $lines,
        Location $location,
    ): ?Example {
        $buffer = [];

        while ($lines->valid()) {
            $line = $lines->current();
            $lines->next();

            if ($this->endOfAFencedCodeBlock($line)) {
                return new Example(
                    \implode(PHP_EOL, $buffer),
                    $location->ofLength($lines->key()),
                );
            }

            $buffer[] = \preg_replace("/^\s*\*( ?)/", "", $line);
        }

        return null;
    }

    /**
     * @param \ArrayIterator<int,string> $lines
     * phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    private function findFencedPHPCodeBlockStart(\ArrayIterator $lines): ?int
    {
        $insideAFencedCodeBlock = false;

        while ($lines->valid()) {
            $line = $lines->current();
            $lines->next();

            if ($insideAFencedCodeBlock) {
                if ($this->endOfAFencedCodeBlock($line)) {
                    $insideAFencedCodeBlock = false;
                }
            } else {
                $lang = $this->startOfAFencedCodeBlock($line);

                if ($lang === false) {
                    continue;
                }

                if ($this->isAcceptedLanguage($lang)) {
                    return $lines->key();
                }

                $insideAFencedCodeBlock = true;
            }
        }

        return null;
    }

    private function isAcceptedLanguage(string $lang): bool
    {
        if ($this->acceptedLanguages === null) {
            return true;
        }

        return \in_array(needle: $lang, haystack: $this->acceptedLanguages, strict: true);
    }

    private function endOfAFencedCodeBlock(string $line): bool
    {
        return \ltrim($line) === "* ```";
    }

    private function startOfAFencedCodeBlock(string $line): false|string
    {
        $line = \trim($line);

        if (!\str_starts_with($line, "* ```")) {
            return false;
        }

        return \substr($line, 5);
    }
}
