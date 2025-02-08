<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\Example;
use TH\DocTest\Location;
use TH\Maybe\Option;

final class AllExamples implements Examples
{
    /**
     * @param Option<array<string>> $languageFilter Use empty string for unspecified language
     */
    public function __construct(
        private readonly Comments $comments,
        private readonly Option $languageFilter,
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
     * @param Option<array<string>> $languageFilter Use empty string for unspecified language
     */
    public static function fromPaths(
        array $paths,
        Option $languageFilter,
    ): self {
        return new self(
            SourceComments::fromPaths($paths),
            $languageFilter,
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

        while (($example = $this->nextExample($lines, $location, $index++))->isSome()) {
            yield $example->unwrap();
        }
    }

    /**
     * @param \ArrayIterator<int,string> $lines
     * @return Option<Example>
     */
    private function nextExample(\ArrayIterator $lines, Location $location, int $index): Option
    {
        return $this->findFencedCodeBlockStart($lines)->andThen(
            fn (int $codeblockStartedAt)
                => $this->readExample($lines, $location->startingAt($codeblockStartedAt, $index)),
        );
    }

    /**
     * @param \ArrayIterator<int,string> $lines
     * @return Option<Example>
     */
    private function readExample(\ArrayIterator $lines, Location $location): Option
    {
        $buffer = [];

        while ($lines->valid()) {
            $line = $lines->current();
            $lines->next();

            if ($this->endOfAFencedCodeBlock($line)) {
                return Option\some(new Example(\implode(PHP_EOL, $buffer), $location->ofLength($lines->key())));
            }

            $buffer[] = \preg_replace("/^\s*\*( ?)/", "", $line);
        }

        return Option\none();
    }

    /**
     * @param \ArrayIterator<int,string> $lines
     * phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     * @return Option<int>
     */
    private function findFencedCodeBlockStart(\ArrayIterator $lines): Option
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

                if ($lang->mapOr($this->isAcceptedLanguage(...), default: false)) {
                    return Option\some($lines->key());
                }

                if ($lang->isNone()) {
                    continue;
                }

                $insideAFencedCodeBlock = true;
            }
        }

        return Option\none();
    }

    private function isAcceptedLanguage(string $lang): bool
    {
        return $this->languageFilter->mapOr(
            callback: static fn (array $languages) => \in_array(needle: $lang, haystack: $languages, strict: true),
            default: true,
        );
    }

    private function endOfAFencedCodeBlock(string $line): bool
    {
        return \ltrim($line) === "* ```";
    }

    /**
     * @return Option<string>
     */
    private function startOfAFencedCodeBlock(string $line): Option
    {
        $line = \trim($line);

        if (!\str_starts_with($line, "* ```")) {
            return Option\none();
        }

        return Option\some(\substr($line, 5));
    }
}
