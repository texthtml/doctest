<?php

declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Event;
use TH\DocTest\Mago\Issue;
use TH\DocTest\Mago\IssuesFound;
use TH\DocTest\Mago\Runner;
use TH\DocTest\TestCase;
use TH\DocTest\TestCase\Example;

/**
 * replaces {@see TestExecutor} when checking examples with Mago instead of
 * running them: an example fails when Mago reported issues on it
 */
final class MagoChecker implements EventSubscriberInterface
{
    public function __construct(
        /** @var \WeakMap<Example, list<Issue>> */
        private readonly \WeakMap $issues,
    ) {}

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
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Event\ExecuteTest::class => 'execute',
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
