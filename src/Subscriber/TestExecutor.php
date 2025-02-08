<?php declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Event;
use TH\DocTest\TestCase\ExpectedFailure;
use Webmozart\Assert\Assert;

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
        $expectedFailure = $event->test->expectedFailure();

        \ob_start();

        try {
            $expectedFailure === null
                ? $event->test->eval()
                : self::assertThrows($event->test->eval(...), $expectedFailure);
        } finally {
            $output = \ob_get_clean();
            \assert(\is_string($output), "example messed up with output buffers");

            $lines = \array_map(\trim(...), \explode(PHP_EOL, $output));
            $output = \implode(PHP_EOL, \array_filter($lines, static fn (string $line): bool => $line !== ""));

            $expectedOutput = $event->test->expectedOutput();
            Assert::same(
                $output,
                $expectedOutput,
                $expectedOutput === ""
                    ? "Expected no output. Got: %s"
                    : "Expected output to be %2\$s. Got: %s",
            );
        }
    }

    /**
     * @param callable(): void $callable
     * @throws \InvalidArgumentException
     */
    private static function assertThrows(callable $callable, ExpectedFailure $expectedFailure): void
    {
        try {
            $callable();
        } catch (\Throwable $actual) {
            if (!($actual instanceof $expectedFailure->class)) {
                $actual = $actual::class;

                throw new \InvalidArgumentException(
                    "Expected to throw \"{$expectedFailure->class}\", got \"{$actual}\"",
                );
            }

            Assert::same($actual->getMessage(), $expectedFailure->message);

            return;
        }

        throw new \InvalidArgumentException(\sprintf(
            "Expected to throw \"{$expectedFailure->class}\", got none",
        ));
    }
}
