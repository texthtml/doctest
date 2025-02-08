<?php declare(strict_types=1);

namespace TH\DocTest;

final class Location implements \Stringable
{
    /**
     * @param \ReflectionClass<*>|\ReflectionMethod|\ReflectionFunction $source
     */
    public function __construct(
        public readonly \ReflectionClass|\ReflectionMethod|\ReflectionFunction $source,
        public readonly string $name,
        public readonly ?string $path,
        public readonly ?int $startLine,
        public readonly ?int $endLine,
        public readonly int $index,
    ) {}

    public function startingAt(int $offset, int $index): Location
    {
        return new self(
            $this->source,
            $this->name,
            $this->path,
            $this->startLine !== null ? $this->startLine + $offset : null,
            null,
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
            $this->startLine !== null ? $this->startLine + $length : null,
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

        $startLine = $source->getStartLine();

        if ($startLine !== false) {
            $endLine = $startLine;
            $startLine -= \substr_count($comment, \PHP_EOL);
        } else {
            $endLine = $startLine = null;
        }

        return new self(
            $source,
            $name,
            self::makePathRelative($source->getFileName()),
            $startLine,
            $endLine,
            1,
        );
    }

    private static function makePathRelative(string|false $path): ?string
    {
        static $stripSrcDirPattern;

        if ($path === false) {
            return null;
        }

        $stripSrcDirPattern ??=
            "/^" .
            \preg_quote(
                str: ($cwd = \getcwd()) !== false
                    ? $cwd
                    : throw new \RuntimeException("getwd failed"),
                delimiter: "/",
            ) .
            "(\/*)/";

        return \preg_replace($stripSrcDirPattern, "", $path) ??
            throw new \RuntimeException("Making path relative failed for : $path");
    }

    public function __toString(): string
    {
        return "{$this->name}#{$this->index} ({$this->path}:{$this->startLine})";
    }
}
