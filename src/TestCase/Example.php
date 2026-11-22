<?php

declare(strict_types=1);

namespace TH\DocTest\TestCase;

use TH\DocTest\Location\CodeLocation;
use TH\DocTest\Location\StrictTypes;
use TH\DocTest\Location\SymbolSource;
use TH\DocTest\TestCase;
use TH\Maybe\Option;

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
        eval($this->header() . (string) new EvaledCode($this->code));
    }

    /**
     * @return Option<ExpectedFailure>
     */
    public function expectedFailure(): Option
    {
        foreach (\explode(PHP_EOL, $this->code) as $line) {
            $expectedFailure = self::expectedFailureFromLine($line);

            if ($expectedFailure->isSome()) {
                return $expectedFailure;
            }
        }

        return Option\none();
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

    /**
     * `namespace …;` + `use …;` prelude the example code is eval'd under, so it
     * resolves the documented symbol's names unqualified, preceded by
     * `declare(strict_types=1);` when the documented file declares it (eval'd
     * code doesn't inherit it, examples would otherwise always run in coercive
     * mode)
     *
     * @internal
     */
    public function header(): string
    {
        [$runtimeNamespace, $useTarget] = $this->runtimeNamespaceAndUseTarget();

        $prefixes = [];

        if (StrictTypes::declaredIn($this->sourceFile())) {
            $prefixes[] = 'declare(strict_types=1);';
        }

        $prefixes[] = "namespace {$runtimeNamespace}\\Example{$this->location->index};";

        if ($useTarget !== '') {
            $prefixes[] = "use {$useTarget};";
        }

        return \implode(separator: '', array: $prefixes);
    }

    private function sourceFile(): string
    {
        $source = $this->location->source;

        return $source instanceof SymbolSource ? $source->path : (string) $source->getFileName();
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
            $extraNamespacePart = $source->extraNamespace === '' ? '' : "\\{$source->extraNamespace}";

            return ["TH\\Doctest\\Runtime\\{$source->ownerShort}{$extraNamespacePart}", $source->useTarget];
        }

        $extraNamespacePart = $source instanceof \ReflectionMethod ? "\\{$source->getName()}" : '';

        if ($source instanceof \ReflectionMethod) {
            $source = $source->getDeclaringClass();
        }

        $useTarget = $source instanceof \ReflectionFunctionAbstract ? $source->getNamespaceName() : $source->getName();

        return ["TH\\Doctest\\Runtime\\{$source->getName()}{$extraNamespacePart}", $useTarget];
    }

    /**
     * @return Option<ExpectedFailure>
     */
    private static function expectedFailureFromLine(string $line): Option
    {
        $matches = [];

        \preg_match("/\/\/\s*@throws\s*(?<class>[^ ]+)\s+(?<message>[^\s].*[^\s])\s*/", $line, $matches);

        if ($matches === []) {
            return Option\none();
        }

        if (!\is_a($matches['class'], \Throwable::class, allow_string: true)) {
            throw new \RuntimeException("`{$matches['class']}` isn't a `\Throwable`");
        }

        return Option\some(new ExpectedFailure($matches['class'], $matches['message']));
    }

    /**
     * @param array<mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): void
    {
        $name = \preg_replace(pattern: '/^assert/', replacement: '', subject: $name);
        \assert(\is_string($name), description: 'prefix stripping cannot fail');

        \TH\DocTest\Setup\CustomAssertionHandlers::invoke($name, $arguments);
    }
}
