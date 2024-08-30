<?php declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Event;
use TH\DocTest\Location;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type Failure array{class:class-string<\Throwable>,message:string}
 */
final class TestExecutor implements EventSubscriberInterface
{
    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Event\ExecuteTest::class => "execute",
        ];
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function execute(Event\ExecuteTest $event): void
    {
        $code = $this->prefixes($event->example->location);
        $code[] = $originalCode = $event->example->code;

        $test = new TestExecutor\TestCase(\implode("; ", $code));

        $expectedFailure = self::expectedFailure($originalCode);

        \ob_start();

        try {
            $expectedFailure === null
                ? $test->eval()
                : self::assertThrows($test->eval(...), $expectedFailure);
        } finally {
            $output = \ob_get_clean();
            \assert(\is_string($output), "example messed up with output buffers");

            $lines = \array_map(\trim(...), \explode(PHP_EOL, $output));
            $output = \implode(PHP_EOL, \array_filter($lines, static fn (string $line): bool => $line !== ""));

            Assert::same($output, self::expectedOutput($originalCode), 'Expected output to be %2$s. Got: %s');
        }
    }

    /**
     * @param callable(): void $callable
     * @param Failure $expectedFailure
     * @throws \InvalidArgumentException
     */
    private static function assertThrows(callable $callable, array $expectedFailure): void
    {
        try {
            $callable();
        } catch (\Throwable $actual) {
            if (!($actual instanceof $expectedFailure["class"])) {
                $actual = $actual::class;

                throw new \InvalidArgumentException(
                    "Expected to throw \"{$expectedFailure["class"]}\", got \"{$actual}\"",
                );
            }

            Assert::same($actual->getMessage(), $expectedFailure["message"]);

            return;
        }

        throw new \InvalidArgumentException(\sprintf(
            "Expected to throw \"{$expectedFailure["class"]}\", got \"none\"",
        ));
    }

    /**
     * @return ?Failure
     */
    private static function expectedFailure(string $code): ?array
    {
        foreach (\explode(PHP_EOL, $code) as $line) {
            $expectedFailure = self::expectedFailureFromLine($line);

            if ($expectedFailure !== null) {
                return $expectedFailure;
            }
        }

        return null;
    }

    /**
     * @return ?Failure
     */
    private static function expectedFailureFromLine(string $line): ?array
    {
        \preg_match("/\/\/\s*@throws\s*(?<class>[^ ]+)\s+(?<message>[^\s].*[^\s])\s*/", $line, $matches);

        if (!\array_key_exists("class", $matches) || !\array_key_exists("message", $matches)) {
            return null;
        }

        if (!\is_a($matches["class"], \Throwable::class, allow_string: true)) {
            throw new \RuntimeException("`{$matches['class']}` isn't a `\Throwable`");
        }

        return ["class" => $matches["class"], "message" => $matches["message"]];
    }

    private static function expectedOutput(string $code): string
    {
        $expectedOutput = [];

        foreach (\explode(PHP_EOL, $code) as $line) {
            \preg_match("/\/\/\s*@prints\s*(?<text>[^\s].*)$/", $line, $matches);

            if (\array_key_exists("text", $matches)) {
                $expectedOutput[] = $matches["text"];
            }
        }

        return \implode(PHP_EOL, $expectedOutput);
    }

    /**
     * @return list<string>
     */
    private function prefixes(Location $location): array
    {
        $source = $location->source;
        $extraNamespacePart = "";

        if ($source instanceof \ReflectionMethod) {
            $extraNamespacePart = "\\{$source->getName()}";
            $source = $source->getDeclaringClass();
        }

        $prefixes = [
            "namespace TH\\Doctest\\Runtime\\{$source->getName()}{$extraNamespacePart}\\Example{$location->index}",
        ];

        $fqcn = $source instanceof \ReflectionFunctionAbstract
            ? $source->getNamespaceName()
            : $source->getName();

        if ($fqcn !== "") {
            $prefixes[] = "use $fqcn;";
        }

        return $prefixes;
    }
}
