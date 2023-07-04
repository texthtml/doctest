<?php declare(strict_types=1);

namespace TH\DocTest;

use TH\Maybe\Option;

final class Location implements \Stringable
{
    /**
     * @param \ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction $source
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
    ) {
    }

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
     * @param \ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction $source
     */
    public static function fromReflection(
        \ReflectionClass|\ReflectionMethod|\ReflectionFunction $source,
        string $comment,
    ): self {
        $name = $source->getName();

        if ($source instanceof \ReflectionMethod) {
            $name = "{$source->getDeclaringClass()->getName()}::$name(…)";
        }

        /** @var Option<int> $endLine */
        $endLine = Option\fromValue($source->getStartLine(), false);

        $startLine = $endLine->map(
            static fn (int $startLine)
                => $startLine - \substr_count($comment, \PHP_EOL),
        );

        return new self(
            $source,
            $name,
            self::makePathRelative($source->getFileName()),
            $startLine,
            $endLine,
            1,
        );
    }

    /**
     * @return Option<string>
     */
    private static function makePathRelative(string|false $path): Option
    {
        static $stripSrcDirPattern;

        if ($path === false) {
            /** @var Option<string> */
            return Option\none();
        }

        $stripSrcDirPattern ??= "/^" . \preg_quote(
            str: (($cwd = \getcwd()) !== false) ? $cwd : throw new \RuntimeException("getwd failed"),
            delimiter: "/",
        ) . "(\/*)/";

        return Option\some(
            \preg_replace($stripSrcDirPattern, "", $path)
                ?? throw new \RuntimeException(
                    "Making path relative failed for `$path`: " . \preg_last_error_msg(),
                    \preg_last_error(),
                ),
        );
    }

    public function __toString(): string
    {
        $location = $this->path
            ->map(
                fn (string $path)
                    => " (" . $this->startLine
                        ->map(static fn (int $startLine) => ":$startLine")
                        ->unwrapOr("") . ")",
            )
            ->unwrapOr("");

        return "{$this->name}#{$this->index}$location";
    }
}
