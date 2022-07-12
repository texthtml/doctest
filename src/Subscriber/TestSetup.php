<?php declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Attributes;
use TH\DocTest\Event;

final class TestSetup implements EventSubscriberInterface
{
    /** @var array<object> */
    // @phpstan-ignore-next-line Property TH\DocTest\Subscriber\TestSetup::$handles is never read, only written.
    private array $handles = [];

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Event\BeforeTest::class => "beforeTest",
            Event\AfterTest::class => "afterTest",
        ];
    }

    public function beforeTest(Event\BeforeTest $event): void
    {
        $attributes = $event->example->location->source->getAttributes(
            Attributes\ExamplesSetup::class,
            \ReflectionAttribute::IS_INSTANCEOF,
        );

        if ($event->example->location->source instanceof \ReflectionMethod) {
            $attributes = \array_merge(
                $this->attributes($event->example->location->source->getDeclaringClass()),
                $attributes,
            );
        }

        $this->handles = \array_map($this->setup(...), $attributes);
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

    /**
     * @param \ReflectionAttribute<Attributes\ExamplesSetup> $attribute
     */
    private function setup(\ReflectionAttribute $attribute): object
    {
        $setupClass = $attribute->newInstance()->setupClass;

        return new $setupClass();
    }
}
