<?php

declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\Location\CodeLocation;
use TH\DocTest\TestCase;
use TH\Maybe\Option;

final class AllTests implements Tests
{
    public function __construct(
        private readonly Comments $comments,
        /** @var Option<list<string>> Use empty string for unspecified language, and none for any languages */
        private readonly Option $languageFilter,
    ) {}

    /**
     * @return \Traversable<TestCase>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->comments as $comment) {
            if ($comment instanceof TestCase) {
                yield $comment;

                continue;
            }

            yield from $this->iterateComment($comment);
        }
    }

    /**
     * @param Comments $comments comment source (reflection or static backend)
     * @param Option<list<string>> $languageFilter Use empty string for unspecified language, and none for any languages
     */
    public static function from(Comments $comments, Option $languageFilter): self
    {
        return new self($comments, $languageFilter);
    }

    /**
     * @return \Traversable<TestCase>
     */
    private function iterateComment(Comment $comment): \Traversable
    {
        $lines = new \ArrayIterator(\explode(PHP_EOL, $comment->text));
        $index = 1;

        while (($example = $this->nextExample($lines, $comment->location, $index++))->isSome()) {
            yield $example->unwrap();
        }
    }

    /**
     * @param \ArrayIterator<int,string> $lines
     *
     * @return Option<TestCase\Example>
     */
    private function nextExample(\ArrayIterator $lines, CodeLocation $location, int $index): Option
    {
        $codeblockStart = $this->findFencedCodeBlockStart($lines);

        return $codeblockStart->andThen(fn(int $startedAt): Option => $this->readExample(
            $lines,
            $location->startingAt($startedAt)->withIndex($index),
        ));
    }

    /**
     * @param \ArrayIterator<int,string> $lines
     *
     * @return Option<TestCase\Example>
     */
    private function readExample(\ArrayIterator $lines, CodeLocation $location): Option
    {
        $buffer = [];
        $length = 0;

        while ($lines->valid()) {
            $line = $lines->current();
            $lines->next();

            if ($this->endOfAFencedCodeBlock($line)) {
                return Option\some(new TestCase\Example(\implode(PHP_EOL, $buffer), $location->ofLength($length)));
            }

            $length++;

            $replacement = \preg_replace(pattern: "/^\s*\*( ?)/", replacement: '', subject: $line);
            \assert(\is_string($replacement), description: "Stripping leading asterisks failed for: {$line}");

            $buffer[] = $replacement;
        }

        return Option\none();
    }

    /**
     * @param \ArrayIterator<int,string> $lines
     *
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

                continue;
            }

            $lang = $this->startOfAFencedCodeBlock($line);

            if ($lang->isNone()) {
                continue;
            }

            if ($this->isAcceptedLanguage($lang->unwrap())) {
                // the key is null when the fence opens on the comment's last line: no example to read.
                // fromValue() does not strip null from its Option<U> type (texthtml/maybe#103)
                // @mago-expect lint:inline-variable-return
                /** @var Option<int> $codeblockStart */
                $codeblockStart = Option\fromValue($lines->key());

                return $codeblockStart;
            }

            $insideAFencedCodeBlock = true;
        }

        return Option\none();
    }

    private function isAcceptedLanguage(string $lang): bool
    {
        return $this->languageFilter->isNoneOr(static fn(array $languages): bool => \in_array(
            needle: $lang,
            haystack: $languages,
            strict: true,
        ));
    }

    private function endOfAFencedCodeBlock(string $line): bool
    {
        return \ltrim($line) === '* ```';
    }

    /**
     * @return Option<string> the code block language, none if the line does not start a fenced code block
     */
    private function startOfAFencedCodeBlock(string $line): Option
    {
        $line = \trim($line);

        if (!\str_starts_with($line, '* ```')) {
            return Option\none();
        }

        return Option\some(\substr(string: $line, offset: 5));
    }
}
