<?php

declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Event;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\TestCase;

final class JunitOutput implements EventSubscriberInterface
{
    private float $startedAt = 0.0;

    /**
     * @var list<array{classname:string, name:string, time:float, failure:\Throwable|null}>
     */
    private array $results = [];

    public function __construct(
        private readonly string $filename,
    ) {}

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Event\BeforeTest::class => 'beforeTest',
            Event\AfterTestSuccess::class => 'recordSuccess',
            Event\AfterTestFailure::class => 'recordFailure',
            Event\AfterTestSuite::class => 'write',
        ];
    }

    public function beforeTest(): void
    {
        $this->startedAt = \microtime(true);
    }

    public function recordSuccess(Event\AfterTestSuccess $event): void
    {
        $this->record($event->test, null);
    }

    public function recordFailure(Event\AfterTestFailure $event): void
    {
        $this->record($event->test, $event->failure);
    }

    private function record(TestCase $test, ?\Throwable $failure): void
    {
        $location = $test->location();

        if (!$location instanceof CodeLocation) {
            $this->results[] = [
                'classname' => (string) $location,
                'name' => (string) $location,
                'time' => \microtime(true) - $this->startedAt,
                'failure' => $failure,
            ];

            return;
        }

        $this->results[] = [
            'classname' => $location->path,
            'name' => "{$location->name}#{$location->index}",
            'time' => \microtime(true) - $this->startedAt,
            'failure' => $failure,
        ];
    }

    public function write(Event\AfterTestSuite $event): void
    {
        \file_put_contents($this->filename, self::xml($this->results))
            || throw new \RuntimeException("Could not write JUnit output to {$this->filename}");
    }

    /**
     * @param list<array{classname:string, name:string, time:float, failure:\Throwable|null}> $results
     */
    private static function xml(array $results): string
    {
        $failures = \count(\array_filter(\array_column($results, 'failure')));
        $time = \array_sum(\array_column($results, 'time'));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . \PHP_EOL;
        $xml .=
            '<testsuites name="doctest" tests="'
            . \count($results)
            . "\" failures=\"{$failures}\" time=\""
            . self::time($time)
            . '">'
            . \PHP_EOL;

        $groups = [];

        foreach ($results as $result) {
            $groups[$result['classname']] ??= [];
            $groups[$result['classname']][] = $result;
        }

        foreach ($groups as $classname => $testResults) {
            $suiteFailures = \count(\array_filter(\array_column($testResults, 'failure')));

            $xml .=
                '  <testsuite name="'
                . self::escape($classname)
                . '" tests="'
                . \count($testResults)
                . "\" failures=\"{$suiteFailures}\" time=\""
                . self::time(\array_sum(\array_column($testResults, 'time')))
                . '">'
                . \PHP_EOL;

            foreach ($testResults as $result) {
                $xml .=
                    '    <testcase name="'
                    . self::escape($result['name'])
                    . '" classname="'
                    . self::escape($result['classname'])
                    . '" time="'
                    . self::time($result['time'])
                    . '"';

                if ($result['failure'] === null) {
                    $xml .= ' />' . \PHP_EOL;

                    continue;
                }

                $failure = $result['failure'];
                $xml .=
                    '>'
                    . \PHP_EOL
                    . '      <failure type="'
                    . self::escape($failure::class)
                    . '" message="'
                    . self::escape($failure->getMessage())
                    . '">'
                    . self::escape((string) $failure)
                    . '</failure>'
                    . \PHP_EOL
                    . '    </testcase>'
                    . \PHP_EOL;
            }

            $xml .= '  </testsuite>' . \PHP_EOL;
        }

        return $xml . '</testsuites>' . \PHP_EOL;
    }

    private static function time(float $time): string
    {
        return \number_format(num: $time, decimals: 3, thousands_separator: '.');
    }

    private static function escape(string $string): string
    {
        return \htmlspecialchars(string: $string, flags: \ENT_XML1 | \ENT_QUOTES, encoding: 'UTF-8');
    }
}
