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
        EndOfTestSetup::$observations = [];
        EndOfTestSetup::$probe = null;
        \TH\DocTest\Setup\EndOfTestHooks::$hooks = [];
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
        $afterFirstBefore = HookedSetup::$calls;

        $dispatcher->dispatch(new Event\ExecuteTest($example));
        $dispatcher->dispatch(new Event\AfterTest($example));
        $afterFirstExample = HookedSetup::$calls;

        // example 2: the whole lifecycle runs again, freshly
        $dispatcher->dispatch(new Event\BeforeTest($example));
        $dispatcher->dispatch(new Event\ExecuteTest($example));
        $dispatcher->dispatch(new Event\AfterTest($example));
        $afterSecondExample = HookedSetup::$calls;

        static::assertSame(['construct h', 'before h'], $afterFirstBefore);
        static::assertSame(['construct h', 'before h', 'after h'], $afterFirstExample);
        static::assertSame(
            ['construct h', 'before h', 'after h', 'construct h', 'before h', 'after h'],
            $afterSecondExample,
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

    public function testThrowingAfterExampleHookLogsAWarning(): void
    {
        $location = $this->reflectedLocation('ThrowingAfterExample');
        $example = new Example('$answer = 42;', $location);

        $logger = new RecordingLogger();
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new TestSetup($logger));

        $dispatcher->dispatch(new Event\BeforeTest($example));
        $dispatcher->dispatch(new Event\AfterTest($example));

        static::assertSame(
            [
                'TH\DocTest\Tests\Subscriber\ThrowingAfterExample::afterExample() threw LogicException: cleanup must not mask the example outcome',
            ],
            $logger->messages['warning'] ?? [],
        );
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
        $fname =
            'fixture_' . \uniqid() . '_' . (string) \preg_replace(pattern: '/\W+/', replacement: '', subject: $setup);
        $class = "TH\\DocTest\\Tests\\Subscriber\\{$setup}";

        $code = <<<PHP
            #[\TH\DocTest\Attributes\ExamplesSetup(new \\{$class}('h'))]
            function {$fname}(): void {}

            return new \ReflectionFunction('TH\\DocTest\\Tests\\Subscriber\\{$fname}');
            PHP;

        /** @var \ReflectionFunction|false $source */
        $source = eval("namespace TH\\DocTest\\Tests\\Subscriber;\n{$code}");
        \assert($source instanceof \ReflectionFunction, description: 'eval must return a ReflectionFunction');

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
