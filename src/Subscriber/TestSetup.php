<?php

declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Attributes;
use TH\DocTest\Event;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\Location\SymbolSource;
use TH\DocTest\Setup\AfterExample;
use TH\DocTest\Setup\BeforeExample;
use TH\DocTest\Setup\EndOfTest;
use TH\DocTest\Setup\EndOfTestHooks;

final class TestSetup implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

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

            $this->registerEndOfTestHooks();
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

        $this->registerEndOfTestHooks();
        $this->notifyBeforeExample();
    }

    public function afterTest(): void
    {
        $this->notifyAfterExample();
        EndOfTestHooks::$hooks = [];
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
     * EndOfTest hooks must run inside the example's scope (see the interface's
     * doc): the eval'd code reaches them through this static registry
     */
    private function registerEndOfTestHooks(): void
    {
        EndOfTestHooks::$hooks = [];

        foreach ($this->handles as $handle) {
            if ($handle instanceof EndOfTest) {
                EndOfTestHooks::$hooks[] = $handle->endOfTest(...);
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
                } catch (\Throwable $th) {
                    $class = $handle::class;

                    $this->logger->warning('{setup}::afterExample() threw {class}: {message}', [
                        'setup' => $class,
                        'class' => $th::class,
                        'message' => $th->getMessage(),
                    ]);
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
            /** @var mixed $handle eval()'s result is untyped by construction */
            $handle = eval("return {$expression};");
        } catch (\Throwable $previous) {
            throw new \RuntimeException("failed to instantiate setup: {$expression}", previous: $previous);
        }

        \assert(\is_object($handle), description: "setup expression must yield an object: {$expression}");

        return $handle;
    }
}
