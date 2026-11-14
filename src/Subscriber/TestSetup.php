<?php

declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Attributes;
use TH\DocTest\Event;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\Location\SymbolSource;

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
                // eval()'s result is untyped by construction: mixed by annotation,
                // narrowed to object by the guard below
                /** @var mixed $handle */
                $handle = eval("return {$expression};");

                if (!\is_object($handle)) {
                    throw new \RuntimeException("setup expression did not yield an object: {$expression}");
                }

                $this->handles[] = $handle;
            }

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
    }

    public function afterTest(): void
    {
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
}
