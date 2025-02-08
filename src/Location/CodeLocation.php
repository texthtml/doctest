<?php declare(strict_types=1);

namespace TH\DocTest\Location;

final class CodeLocation implements Location
{
    /**
     * @param \ReflectionClass<*>|\ReflectionMethod|\ReflectionFunction $source
     */
    public function __construct(
        public readonly \ReflectionClass|\ReflectionMethod|\ReflectionFunction $source,
        public readonly string $name,
        public readonly string $path,
        public readonly ?int $startLine,
        public readonly ?int $endLine,
        public readonly int $index,
    ) {
    }

    public function startingAt(int $offset): self
    {
        return new self(
            $this->source,
            $this->name,
            $this->path,
            $this->startLine !== null ? $this->startLine + $offset : null,
            $this->endLine,
            $this->index,
        );
    }

    public function withIndex(int $index): self
    {
        return new self($this->source, $this->name, $this->path, $this->startLine, $this->endLine, $index);
    }

    public function ofLength(int $length): self
    {
        return new self(
            $this->source,
            $this->name,
            $this->path,
            $this->startLine,
            $this->startLine !== null ? $this->startLine + $length - 1 : null,
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

        $endLine = $source->getStartLine();

        if ($endLine !== false) {
            $endLine--;
            $endLine = $endLine;
            $startLine = $endLine - \substr_count($comment, \PHP_EOL);
        } else {
            $startLine = $endLine = null;
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

    private static function makePathRelative(string|false $path): string
    {
        static $stripSrcDirPattern;

        if ($path === false) {
            return "<Unknown file location>";
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
        $suffix = $this->path;

        if ($this->startLine !== null) {
            $suffix .= ":{$this->startLine}";

            if ($this->endLine !== $this->startLine) {
                $suffix .= "-{$this->endLine}";
            }
        }

        return "{$this->name}#{$this->index} ($suffix)";
    }
}
