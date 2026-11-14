<?php

declare(strict_types=1);

namespace TH\DocTest\TestCase;

use TH\DocTest\Location\CodeLocation;
use TH\DocTest\Location\SymbolSource;
use TH\DocTest\TestCase;
use Webmozart\Assert\Assert;

final class Example implements TestCase
{
    public function __construct(
        public readonly string $code,
        private readonly CodeLocation $location,
    ) {}

    public function location(): CodeLocation
    {
        return $this->location;
    }

    public function eval(): void
    {
        eval($this->header() . $this->code);
    }

    public function expectedFailure(): ?ExpectedFailure
    {
        foreach (\explode(PHP_EOL, $this->code) as $line) {
            $expectedFailure = self::expectedFailureFromLine($line);

            if ($expectedFailure !== null) {
                return $expectedFailure;
            }
        }

        return null;
    }

    public function expectedOutput(): string
    {
        $expectedOutput = [];

        foreach (\explode(PHP_EOL, $this->code) as $line) {
            $matches = [];

            \preg_match("/\/\/\s*@prints\s*(?<text>[^\s].*)$/", $line, $matches);

            if ($matches !== []) {
                $expectedOutput[] = $matches['text'];
            }
        }

        return \implode(PHP_EOL, $expectedOutput);
    }

    private function header(): string
    {
        $location = $this->location;
        $source = $location->source;

        if ($source instanceof SymbolSource) {
            // mirror the reflection header: the runtime namespace segments off the
            // declaring symbol (class for methods, bare name for class-likes),
            // and the `use` target is the declaring class / the function itself
            $extraNamespacePart = $source->isMethod() ? "\\{$source->ownerShort}" : '';
            $prefixes = [
                "namespace TH\\Doctest\\Runtime\\{$source->ownerShort}{$extraNamespacePart}\\Example{$location->index};",
            ];

            if ($source->useTarget !== '') {
                $prefixes[] = "use {$source->useTarget};";
            }

            return \implode(separator: '', array: $prefixes);
        }

        $extraNamespacePart = $source instanceof \ReflectionMethod ? "\\{$source->getName()}" : '';

        if ($source instanceof \ReflectionMethod) {
            $source = $source->getDeclaringClass();
        }

        $prefixes = [
            "namespace TH\\Doctest\\Runtime\\{$source->getName()}{$extraNamespacePart}\\Example{$location->index};",
        ];

        $fqcn = $source instanceof \ReflectionFunctionAbstract ? $source->getNamespaceName() : $source->getName();

        if ($fqcn !== '') {
            $prefixes[] = "use {$fqcn};";
        }

        return \implode(separator: '', array: $prefixes);
    }

    private static function expectedFailureFromLine(string $line): ?ExpectedFailure
    {
        $matches = [];

        \preg_match("/\/\/\s*@throws\s*(?<class>[^ ]+)\s+(?<message>[^\s].*[^\s])\s*/", $line, $matches);

        if ($matches === []) {
            return null;
        }

        if (!\is_a($matches['class'], \Throwable::class, allow_string: true)) {
            throw new \RuntimeException("`{$matches['class']}` isn't a `\Throwable`");
        }

        return new ExpectedFailure($matches['class'], $matches['message']);
    }

    /**
     * @param array<mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): void
    {
        $name = \preg_replace(pattern: '/^assert/', replacement: '', subject: $name);

        // proxying Webmozart Assert calls made from evaluated examples: the method name is dynamic by design
        // @mago-expect analysis:invalid-member-selector,analysis:string-member-selector
        Assert::$name(...$arguments);
    }
}
