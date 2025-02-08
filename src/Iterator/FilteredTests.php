<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\TestCase;

final class FilteredTests implements Tests
{
    public function __construct(
        private readonly Tests $examples,
        private readonly string $filter,
    ) {}

    /**
     * @return \Traversable<TestCase>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->examples as $example) {
            if (\str_contains((string) $example->location(), $this->filter)) {
                yield $example;
            }
        }
    }

    public static function filter(Tests $examples, string $filter): self
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
            AllTests::fromPaths($paths, $acceptedLanguages),
            $filter,
        );
    }
}
