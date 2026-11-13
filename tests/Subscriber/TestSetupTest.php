<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TH\DocTest\Event;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\Subscriber\TestSetup;
use TH\DocTest\TestCase\Example;

final class TestSetupTest extends TestCase
{
    protected function setUp(): void
    {
        CounterSetup::$instantiations = [];
    }

    public function testSetupsAreInstantiatedFreshForEachExampleWithTheirOwnArguments(): void
    {
        $source = $this->reflectedFunctionWithSetups();

        $location = CodeLocation::fromReflection($source, (string) $source->getDocComment());
        $example = new Example('$answer = 42;', $location);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new TestSetup());

        $dispatcher->dispatch(new Event\BeforeTest($example));
        $dispatcher->dispatch(new Event\ExecuteTest($example));
        $dispatcher->dispatch(new Event\AfterTest($example));
        $dispatcher->dispatch(new Event\BeforeTest($example));
        $dispatcher->dispatch(new Event\ExecuteTest($example));
        $dispatcher->dispatch(new Event\AfterTest($example));

        static::assertSame(['first', 'second', 'third', 'first', 'second', 'third'], CounterSetup::$instantiations);
    }

    private function reflectedFunctionWithSetups(): \ReflectionFunction
    {
        $setup = CounterSetup::class;

        $code = <<<PHP
            return new \ReflectionFunction(
                #[\TH\DocTest\Attributes\ExamplesSetup([new {$setup}('first'), new {$setup}('second')])]
                #[\TH\DocTest\Attributes\ExamplesSetup(new {$setup}('third'))]
                static function (): void {},
            );
            PHP;

        /** @var \ReflectionFunction|false $source */
        $source = eval($code);
        \assert($source instanceof \ReflectionFunction, description: 'eval must return a ReflectionFunction');

        return $source;
    }
}
