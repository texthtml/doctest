<?php declare(strict_types=1);

namespace TH\DocTest\TestCase;

use TH\DocTest\Location\CodeLocation;
use TH\DocTest\TestCase;
use Webmozart\Assert\Assert;

final readonly class Example implements TestCase
{
    public function __construct(
        public string $code,
        private CodeLocation $location,
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
            \preg_match("/\/\/\s*@prints\s*(?<text>[^\s].*)$/", $line, $matches);

            if (\array_key_exists("text", $matches)) {
                $expectedOutput[] = $matches["text"];
            }
        }

        return \implode(PHP_EOL, $expectedOutput);
    }

    private function header(): string
    {
        $location = $this->location;
        $source = $location->source;
        $extraNamespacePart = "";

        if ($source instanceof \ReflectionMethod) {
            $extraNamespacePart = "\\{$source->getName()}";
            $source = $source->getDeclaringClass();
        }

        $prefixes = [
            "namespace TH\\Doctest\\Runtime\\{$source->getName()}{$extraNamespacePart}\\Example{$location->index};",
        ];

        $fqcn = $source instanceof \ReflectionFunctionAbstract
            ? $source->getNamespaceName()
            : $source->getName();

        if ($fqcn !== "") {
            $prefixes[] = "use $fqcn;";
        }

        return \implode(separator: '', array: $prefixes);
    }

    private static function expectedFailureFromLine(string $line): ?ExpectedFailure
    {
        \preg_match("/\/\/\s*@throws\s*(?<class>[^ ]+)\s+(?<message>[^\s].*[^\s])\s*/", $line, $matches);

        if (!\array_key_exists("class", $matches) || !\array_key_exists("message", $matches)) {
            return null;
        }

        if (!\is_a($matches["class"], \Throwable::class, allow_string: true)) {
            throw new \RuntimeException("`{$matches['class']}` isn't a `\Throwable`");
        }

        return new ExpectedFailure($matches["class"], $matches["message"]);
    }

    /**
     * @param array<mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): void
    {
        $name = \preg_replace("/^assert/", "", $name);

        // @phpstan-ignore-next-line Variable static method call on Webmozart\Assert\Assert.
        Assert::$name(...$arguments);
    }
}
