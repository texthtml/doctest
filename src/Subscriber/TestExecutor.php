<?php declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Event;
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
        $test = new TestExecutor\TestCase($code = $event->example->code);

        $expectedFailure = self::expectedFailure($code);

        $output = $expectedFailure === null
            ? $test->eval()
            : self::assertThrows($test->eval(...), $expectedFailure);

        Assert::same($output, self::expectedOutput($code));
    }

    /**
     * @param callable(): string $callable
     * @param Failure $expectedFailure
     * @throws \InvalidArgumentException
     */
    private static function assertThrows(callable $callable, array $expectedFailure): string
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

            return "";
        }

        throw new \InvalidArgumentException(\sprintf(
            "Expected to throw \"{$expectedFailure["class"]}\", got \"none\"",
        ));
    }

    /**
     * @return Failure
     */
    private static function expectedFailure(string $code): ?array
    {
        foreach (\explode(PHP_EOL, $code) as $line) {
            \preg_match("/\/\/\s*@throws\s*(?<class>[^ ]+)\s+(?<message>[^\s].*[^\s])\s*/", $line, $matches);

            if (\array_key_exists("class", $matches)) {
                return ["class" => $matches["class"], "message" => $matches["message"]];
            }
        }

        return null;
    }

    private static function expectedOutput(string $code): string
    {
        return "";
    }
}
