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
        HookedSetup::$calls = [];
        ThrowingAfterExample::$calls = [];
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

    public function testHookedSetupsAreCalledBeforeAndAfterEachExample(): void
    {
        $location = $this->reflectedLocation('HookedSetup');
        $example = new Example('$answer = 42;', $location);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new TestSetup());

        // example 1: fresh instance, before hook, then the after hook at teardown
        $dispatcher->dispatch(new Event\BeforeTest($example));
        static::assertSame(['construct h', 'before h'], HookedSetup::$calls);

        $dispatcher->dispatch(new Event\ExecuteTest($example));
        $dispatcher->dispatch(new Event\AfterTest($example));
        static::assertSame(['construct h', 'before h', 'after h'], HookedSetup::$calls);

        // example 2: the whole lifecycle runs again, freshly
        $dispatcher->dispatch(new Event\BeforeTest($example));
        $dispatcher->dispatch(new Event\ExecuteTest($example));
        $dispatcher->dispatch(new Event\AfterTest($example));
        static::assertSame(
            ['construct h', 'before h', 'after h', 'construct h', 'before h', 'after h'],
            HookedSetup::$calls,
        );
    }

    public function testAfterExampleRunsEvenWhenTheExampleFailed(): void
    {
        $location = $this->reflectedLocation('ThrowingAfterExample');
        $example = new Example('$answer = 42;', $location);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new TestSetup());

        // failing example: the runner dispatches AfterTest from its catch path,
        // and AfterExample::afterExample() must still have been called
        $dispatcher->dispatch(new Event\BeforeTest($example));
        $dispatcher->dispatch(new Event\AfterTest($example));
        static::assertSame(['construct', 'after'], ThrowingAfterExample::$calls);
    }

    private function reflectedFunctionWithSetups(): \ReflectionFunction
    {
        $setup = CounterSetup::class;

        $code = <<<PHP
            return new \ReflectionFunction(
                #[\TH\DocTest\Attributes\ExamplesSetup(new {$setup}('first'))]
                #[\TH\DocTest\Attributes\ExamplesSetup(new {$setup}('second'))]
                #[\TH\DocTest\Attributes\ExamplesSetup(new {$setup}('third'))]
                static function (): void {},
            );
            PHP;

        return $this->reflect($code);
    }

    private function reflectedLocation(string $setup): CodeLocation
    {
        $code = <<<PHP
            return new \ReflectionFunction(
                #[\TH\DocTest\Attributes\ExamplesSetup(new TH\DocTest\Tests\Subscriber\\{$setup}('h'))]
                static function (): void {},
            );
            PHP;

        $source = $this->reflect($code);

        return CodeLocation::fromReflection($source, (string) $source->getDocComment());
    }

    private function reflect(string $code): \ReflectionFunction
    {
        /** @var \ReflectionFunction|false $source */
        $source = eval($code);
        \assert($source instanceof \ReflectionFunction, description: 'eval must return a ReflectionFunction');

        return $source;
    }
}
