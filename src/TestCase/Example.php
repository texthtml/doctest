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
        [$runtimeNamespace, $useTarget] = $this->runtimeNamespaceAndUseTarget();

        $prefixes = [
            "namespace {$runtimeNamespace}\\Example{$this->location->index};",
        ];

        if ($useTarget !== '') {
            $prefixes[] = "use {$useTarget};";
        }

        return \implode(separator: '', array: $prefixes);
    }

    /**
     * the generated namespace segments off the declaring symbol (class for
     * methods, bare name for class-likes), and the `use` target is the
     * declaring class / the function itself — mirroring what reflection
     * exposes, so examples resolve the analyzed code's names unqualified
     *
     * @return array{string, string}
     */
    private function runtimeNamespaceAndUseTarget(): array
    {
        $source = $this->location->source;

        if ($source instanceof SymbolSource) {
            $extraNamespacePart = $source->isMethod() ? "\\{$source->ownerShort}" : '';

            return ["TH\\Doctest\\Runtime\\{$source->ownerShort}{$extraNamespacePart}", $source->useTarget];
        }

        $extraNamespacePart = $source instanceof \ReflectionMethod ? "\\{$source->getName()}" : '';

        if ($source instanceof \ReflectionMethod) {
            $source = $source->getDeclaringClass();
        }

        $useTarget = $source instanceof \ReflectionFunctionAbstract ? $source->getNamespaceName() : $source->getName();

        return ["TH\\Doctest\\Runtime\\{$source->getName()}{$extraNamespacePart}", $useTarget];
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
