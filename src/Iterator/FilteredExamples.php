<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\Example;
use TH\Maybe\Option;

final class FilteredExamples implements Examples
{
    public function __construct(
        private readonly Examples $examples,
        private readonly string $filter,
    ) {}

    /**
     * @return \Traversable<Example>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->examples as $example) {
            if (\str_contains((string) $example->location, $this->filter)) {
                yield $example;
            }
        }
    }

    public static function filter(Examples $examples, string $filter): self
    {
        return new self($examples, $filter);
    }

    /**
     * @param array<string> $paths paths to files and folder to look for PHP comments code examples in
     * @param Option<array<string>> $languageFilter Use empty string for unspecified language
     */
    public static function fromPaths(
        array $paths,
        string $filter,
        Option $languageFilter,
    ): self {
        return self::filter(
            AllExamples::fromPaths($paths, $languageFilter),
            $filter,
        );
    }
}
