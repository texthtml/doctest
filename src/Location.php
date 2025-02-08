<?php declare(strict_types=1);

namespace TH\DocTest;

use TH\Maybe\Option;

final class Location implements \Stringable
{
    /**
     * @param \ReflectionClass<*>|\ReflectionMethod|\ReflectionFunction $source
     * @param Option<string> $path,
     * @param Option<int> $startLine,
     * @param Option<int> $endLine,
     */
    public function __construct(
        public readonly \ReflectionClass|\ReflectionMethod|\ReflectionFunction $source,
        public readonly string $name,
        /** @var Option<string> */
        public readonly Option $path,
        /** @var Option<int> */
        public readonly Option $startLine,
        /** @var Option<int> */
        public readonly Option $endLine,
        public readonly int $index,
    ) {}

    public function startingAt(int $offset, int $index): Location
    {
        return new self(
            $this->source,
            $this->name,
            $this->path,
            $this->startLine->map(static fn (int $startLine) => $startLine + $offset),
            Option\none(),
            $index,
        );
    }

    public function ofLength(int $length): Location
    {
        return new self(
            $this->source,
            $this->name,
            $this->path,
            $this->startLine,
            $this->startLine->map(static fn (int $startLine) => $startLine + $length),
            $this->index,
        );
    }

    /**
     * @param \ReflectionClass<*>|\ReflectionMethod|\ReflectionFunction $source
     */
    public static function fromReflection(
        \ReflectionClass|\ReflectionMethod|\ReflectionFunction $source,
        string $comment,
    ): self {
        $name = $source->getName();

        if ($source instanceof \ReflectionMethod) {
            $name = "{$source->getDeclaringClass()->getName()}::$name(…)";
        }

        $endLine = Option\fromValue($source->getStartLine(), noneValue: false);
        $startLine = $endLine->map(static fn (int $endLine) => $endLine - \substr_count($comment, \PHP_EOL));

        return new self(
            $source,
            $name,
            self::makePathRelative(Option\fromValue($source->getFileName(), noneValue: false)),
            $startLine,
            $endLine,
            1,
        );
    }

    /**
     * @param Option<string> $path
     * @return Option<string>
     */
    private static function makePathRelative(Option $path): Option
    {
        static $stripSrcDirPattern;

        $stripSrcDirPattern ??=
            "/^" .
            \preg_quote(
                str: ($cwd = \getcwd()) !== false
                    ? $cwd
                    : throw new \RuntimeException("getwd failed"),
                delimiter: "/",
            ) .
            "(\/*)/";

        return $path->map(
            static fn (string $path) => \preg_replace($stripSrcDirPattern, "", $path) ??
                throw new \RuntimeException("Making path relative failed for : $path"),
        );
    }

    public function __toString(): string
    {
        $suffix = $this->path
            ->map(
                fn (string $path) => $this->startLine->mapOr(
                    static fn (int $startLine) => "$path:$startLine",
                    $path,
                ),
            )
            ->map(static fn (string $suffix) => " ($suffix)")
            ->unwrapOr("");

        return "{$this->name}#{$this->index}$suffix";
    }
}
