<?php

declare(strict_types=1);

namespace TH\DocTest\Mago;

use TH\DocTest\TestCase\Example;

/**
 * an example as a standalone PHP file Mago can check, mirroring what gets
 * eval'd: the example's `namespace …; use …;` header, then its code as the body
 * of a method of a class extending {@see AssertionsStub} (at runtime the code
 * runs inside {@see Example::eval()}, where `self::assert*()` resolve)
 *
 * the whole prelude fits on the file's first line, so the example's line `n`
 * is the file's line `n + 1`; `use` statements are hoisted to that first line
 * (they are invalid inside a method) and leave an empty line behind
 *
 * @internal
 */
final class ExportedExample
{
    /**
     * Mago issue levels, by increasing severity
     */
    public const LEVELS = ['note' => 1, 'help' => 2, 'warning' => 3, 'error' => 4];

    private const CLASS_NAME = 'DoctestExample';

    /**
     * rules about files are meaningless for code that is eval'd, and only
     * triggered by the wrapper class sharing the exported file with the
     * example's declarations
     */
    private const FILE_RULES = ['single-class-per-file', 'file-name', 'strict-types'];

    /** @var list<string> */
    private readonly array $lines;

    /** @var AssertionsStub::STRICT|AssertionsStub::PERMISSIVE */
    private readonly string $parent;

    public function __construct(
        public readonly Example $example,
    ) {
        $this->lines = \explode(PHP_EOL, $example->code);
        $this->parent = AssertionsStub::for($example);
    }

    public function source(): string
    {
        $uses = [];
        $body = [];

        foreach ($this->lines as $line) {
            $isUse = \preg_match('/^\s*use\s+.*;\s*$/', $line) === 1;

            if ($isUse) {
                $uses[] = \trim($line);
            }

            $body[] = $isUse ? '' : $line;
        }

        $class = self::CLASS_NAME;

        return (
            '<?php '
            . $this->example->header()
            . \implode(separator: '', array: $uses)
            . " final class {$class} extends \\{$this->parent} { public function __invoke() {"
            . PHP_EOL
            . \implode(PHP_EOL, $body)
            . PHP_EOL
            . '}}'
            . PHP_EOL
        );
    }

    /**
     * turns an issue Mago reported on the exported file into one located in the
     * documented file, or null when it should not fail the example: below the
     * project's `minimum-fail-level`, or about the generated scaffolding rather
     * than the example's code
     *
     * @param int $line 1-based line in the exported file
     * @param key-of<self::LEVELS> $minimumFailLevel
     */
    public function issue(string $level, string $code, string $message, int $line, string $minimumFailLevel): ?Issue
    {
        $exampleLine = $this->exampleLine($line, $code);

        if (
            $exampleLine === null
            || (self::LEVELS[$level] ?? 0) < self::LEVELS[$minimumFailLevel]
            || \in_array($code, self::FILE_RULES, strict: true)
            || $this->isCustomAssertion($code, $message)
        ) {
            return null;
        }

        // the generated class is what `self` is in the example; its name
        // (namespaced after the runtime one) is an implementation detail
        $pattern = '/`[\w\\\\]*\\\\' . self::CLASS_NAME . '`/';
        $message = \preg_replace($pattern, replacement: '`self`', subject: $message) ?? $message;

        return new Issue($level, $code, $message, $this->example->location(), $exampleLine);
    }

    /**
     * @return int|null 1-based line in the example, null when on the generated
     *     scaffolding (the prelude / closing braces)
     */
    private function exampleLine(int $line, string $code): ?int
    {
        $exampleLine = $line - 1;
        $count = \count($this->lines);

        if ($exampleLine >= 1 && $exampleLine <= $count) {
            return $exampleLine;
        }

        // issues on the scaffolding are about the generated wrapper
        // (strict_types, class naming…), except syntax errors: an unclosed
        // construct is only detected once the wrapper closes it
        return $code === 'parse' ? \max(1, \min($exampleLine, $count)) : null;
    }

    /**
     * a call handled by the permissive stub's `__callStatic()`
     */
    private function isCustomAssertion(string $code, string $message): bool
    {
        return (
            $code === 'non-documented-method'
            && $this->parent === AssertionsStub::PERMISSIVE
            && \preg_match('/`assert\w*`.*\\\\' . self::CLASS_NAME . '`/', $message) === 1
        );
    }
}
