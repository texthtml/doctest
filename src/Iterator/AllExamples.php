<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\Example;

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
            $buffer = [];
            $index = 1;
            $codeblockStartedAt = null;

            foreach (\explode(PHP_EOL, $comment) as $n => $line) {
                if (\ltrim($line) === "* ```") {
                    if ($codeblockStartedAt !== null) {
                        yield new Example(\implode(PHP_EOL, $buffer), $location->at($codeblockStartedAt, $n, $index++));

                        $codeblockStartedAt = null;
                    } else {
                        $codeblockStartedAt = $n;
                    }

                    $buffer = [];
                } elseif ($codeblockStartedAt !== null) {
                    $buffer[] = \preg_replace("/^\s*\*( ?)/", "", $line);
                }
            }
        }
    }

    /**
     * @param array<string> $paths paths to files and folder to look for PHP comments code examples in
     */
    public static function fromPaths(array $paths): self
    {
        return new self(Comments::fromPaths($paths));
    }
}
