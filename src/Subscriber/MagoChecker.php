<?php

declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Event;
use TH\DocTest\Iterator\LazyLoadedTests;
use TH\DocTest\Iterator\ListedTests;
use TH\DocTest\Iterator\Tests;
use TH\DocTest\Mago\Issue;
use TH\DocTest\Mago\IssuesFound;
use TH\DocTest\Mago\Runner;
use TH\DocTest\TestCase;
use TH\DocTest\TestCase\Example;

/**
 * fails the examples Mago reported issues on. When they also run, this comes
 * first ({@see TestExecutor} has a lower priority): an example with issues
 * fails with them, without being executed
 */
final class MagoChecker implements EventSubscriberInterface
{
    public function __construct(
        /** @var \ArrayAccess<Example, list<Issue>> the issues failing each example */
        private readonly \ArrayAccess $issues,
    ) {}

    /**
     * runs Mago once, up front, on all the examples among the tests, without
     * loading any file
     *
     * @param LoggerInterface|null $lazyLoadLogger when the examples also run
     *     after a static scan: their files are lazily loaded again right before
     *     them (load failures logged there)
     *
     * @return array{Tests, self} the tests to run (the same ones), and the checker
     */
    public static function checkTests(Tests $tests, Runner $mago, ?LoggerInterface $lazyLoadLogger = null): array
    {
        $tests = \iterator_to_array($tests, preserve_keys: false);
        $listed = new ListedTests($tests);

        return [
            $lazyLoadLogger === null ? $listed : new LazyLoadedTests($listed, $lazyLoadLogger),
            self::check($tests, $mago),
        ];
    }

    /**
     * runs Mago once on all the examples among the tests
     *
     * @param list<TestCase> $tests
     */
    public static function check(array $tests, Runner $mago): self
    {
        return new self($mago->check(\array_values(\array_filter(
            $tests,
            static fn(TestCase $test): bool => $test instanceof Example,
        ))));
    }

    /**
     * @return array<string,array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Event\ExecuteTest::class => ['execute', 10],
        ];
    }

    public function execute(Event\ExecuteTest $event): void
    {
        $test = $event->test;

        if ($test instanceof TestCase\SourceError) {
            // the documented file itself could not be parsed
            $test->eval();
        }

        if (!$test instanceof Example) {
            return;
        }

        $issues = $this->issues[$test] ?? [];

        if ($issues !== []) {
            throw new IssuesFound($issues);
        }
    }
}
