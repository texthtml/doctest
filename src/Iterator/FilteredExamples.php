<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\Example;

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
     * @param list<string>|null $acceptedLanguages Use empty string for unspecified language, and null for any languages
     */
    public static function fromPaths(
        array $paths,
        string $filter,
        ?array $acceptedLanguages,
    ): self {
        return self::filter(
            AllExamples::fromPaths($paths, $acceptedLanguages),
            $filter,
        );
    }
}
