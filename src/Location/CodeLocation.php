<?php

declare(strict_types=1);

namespace TH\DocTest\Location;

use TH\Maybe\Option;

final class CodeLocation implements Location
{
    /**
     * the six fields describe a doc-comment example's position precisely; splitting them into a
     * smaller shape would move the verbosity, not remove it
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public function __construct(
        /**
         * @var \ReflectionClass<*>|\ReflectionMethod|\ReflectionFunction|SymbolSource
         */
        public readonly \ReflectionClass|\ReflectionMethod|\ReflectionFunction|SymbolSource $source,
        public readonly string $name,
        public readonly string $path,
        /** @var Option<int> */
        public readonly Option $startLine,
        /** @var Option<int> */
        public readonly Option $endLine,
        public readonly int $index,
    ) {}

    public function startingAt(int $offset): self
    {
        return new self(
            $this->source,
            $this->name,
            $this->path,
            $this->startLine->map(static fn(int $startLine): int => $startLine + $offset),
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
            $this->startLine->map(static fn(int $startLine): int => $startLine + $length - 1),
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
            $name = "{$source->getDeclaringClass()->getName()}::{$name}(…)";
        }

        // fromValue() does not strip false from its Option<U> type (texthtml/maybe#103)
        /** @var Option<int> $declarationLine */
        $declarationLine = Option\fromValue($source->getStartLine(), noneValue: false);
        $endLine = $declarationLine->map(static fn(int $declarationLine): int => $declarationLine - 1);
        $startLine = $endLine->map(static fn(int $endLine): int => $endLine - \substr_count($comment, \PHP_EOL));

        return new self($source, $name, self::makePathRelative($source->getFileName()), $startLine, $endLine, 1);
    }

    public static function fromSymbol(SymbolSource $source, string $comment): self
    {
        $endLine = $source->docEndLine ?? 1;
        $startLine = $endLine - \substr_count($comment, \PHP_EOL);

        return new self(
            $source,
            $source->name,
            self::relative($source->path),
            Option\some($startLine),
            Option\some($endLine),
            1,
        );
    }

    private static function relative(string $path): string
    {
        $cwd = \getcwd();

        if ($cwd === false) {
            return $path;
        }

        return (
            \preg_replace(
                pattern: '/^' . \preg_quote(str: $cwd, delimiter: '/') . '\/?/',
                replacement: '',
                subject: $path,
            ) ?? $path
        );
    }

    private static function makePathRelative(string|false $path): string
    {
        static $stripSrcDirPattern = null;

        if ($path === false) {
            return '<Unknown file location>';
        }

        if ($stripSrcDirPattern === null) {
            $stripSrcDirPattern = self::stripSrcDirPattern();
        }

        return (
            \preg_replace(pattern: $stripSrcDirPattern, replacement: '', subject: $path) ?? throw new \RuntimeException(
                "Making path relative failed for: {$path}",
            )
        );
    }

    private static function stripSrcDirPattern(): string
    {
        $cwd = \getcwd();

        if ($cwd === false) {
            throw new \RuntimeException('getcwd failed');
        }

        return '/^' . \preg_quote(str: $cwd, delimiter: '/') . "(\/*)/";
    }

    public function __toString(): string
    {
        $lines = $this->startLine->mapOr(fn(int $startLine): string => $this->endLine->mapOr(
            static fn(int $endLine): string => $endLine === $startLine ? ":{$startLine}" : ":{$startLine}-{$endLine}",
            ":{$startLine}-",
        ), '');
        $suffix = $this->path . $lines;

        return "{$this->name}#{$this->index} ({$suffix})";
    }
}
