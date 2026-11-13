<?php

declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Attributes;
use TH\DocTest\Event;
use TH\DocTest\Location\CodeLocation;

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

        $attributes = $location->source->getAttributes(
            Attributes\ExamplesSetup::class,
            \ReflectionAttribute::IS_INSTANCEOF,
        );

        if ($location->source instanceof \ReflectionMethod) {
            $attributes = \array_merge($this->attributes($location->source->getDeclaringClass()), $attributes);
        }

        $this->handles = [];

        foreach ($attributes as $attribute) {
            $setups = $attribute->newInstance()->setups;

            foreach (\is_array($setups) ? $setups : [$setups] as $setup) {
                $this->handles[] = $setup;
            }
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
