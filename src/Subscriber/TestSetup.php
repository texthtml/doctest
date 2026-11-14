<?php

declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Attributes;
use TH\DocTest\Event;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\Location\SymbolSource;
use TH\DocTest\Setup\AfterExample;
use TH\DocTest\Setup\BeforeExample;

final class TestSetup implements EventSubscriberInterface
{
    /**
     * keeps the setup objects alive for the duration of the example: releasing them
     * earlier would run their destructors mid-test
     *
     * @var list<object>
     */
    private array $handles = [];

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Event\BeforeTest::class => 'beforeTest',
            Event\AfterTest::class => 'afterTest',
        ];
    }

    public function beforeTest(Event\BeforeTest $event): void
    {
        $location = $event->test->location();

        if (!$location instanceof CodeLocation) {
            return;
        }

        $this->handles = [];

        if ($location->source instanceof SymbolSource) {
            // static backend: setup expressions were captured at scan time,
            // name-resolved so `new \Ns\Setup(…)` works from any namespace
            foreach ($location->source->setups as $expression) {
                $this->handles[] = $this->instantiate($expression);
            }

            $this->notifyBeforeExample();

            return;
        }

        $attributes = $location->source->getAttributes(
            Attributes\ExamplesSetup::class,
            \ReflectionAttribute::IS_INSTANCEOF,
        );

        if ($location->source instanceof \ReflectionMethod) {
            $attributes = \array_merge($this->attributes($location->source->getDeclaringClass()), $attributes);
        }

        foreach ($attributes as $attribute) {
            $this->handles[] = $attribute->newInstance()->setup;
        }

        $this->notifyBeforeExample();
    }

    public function afterTest(): void
    {
        $this->notifyAfterExample();

        $this->handles = [];
    }

    /**
     * @param \ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction $reflection
     * @return array<\ReflectionAttribute<Attributes\ExamplesSetup>>
     */
    private function attributes(\ReflectionClass|\ReflectionMethod|\ReflectionFunction $reflection): array
    {
        return $reflection->getAttributes(Attributes\ExamplesSetup::class, \ReflectionAttribute::IS_INSTANCEOF);
    }

    private function notifyBeforeExample(): void
    {
        foreach ($this->handles as $handle) {
            if ($handle instanceof BeforeExample) {
                $handle->beforeExample();
            }
        }
    }

    /**
     * a hook throwing during cleanup must not mask the example's own outcome:
     * on a failed example an exception here would replace the real failure in
     * the report
     */
    private function notifyAfterExample(): void
    {
        foreach ($this->handles as $handle) {
            if ($handle instanceof AfterExample) {
                try {
                    $handle->afterExample();
                } catch (\Throwable) {
                    // the setup objects are about to be released anyway: swallowing
                    // keeps the example's result authoritative
                }
            }
        }
    }

    /**
     * a raw Throwable out of eval (fatal `Error`, ParseError…) would bypass the
     * runner's expected-failure handling: wrapping keeps setup problems
     * attributable to the example's location rather than crashing the process
     */
    private function instantiate(string $expression): object
    {
        try {
            $handle = eval("return {$expression};");
        } catch (\Throwable $previous) {
            throw new \RuntimeException("failed to instantiate setup: {$expression}", previous: $previous);
        }

        \assert(\is_object($handle), description: "setup expression must yield an object: {$expression}");

        return $handle;
    }
}
