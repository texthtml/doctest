<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TH\DocTest\Event;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\Subscriber\TestExecutor;
use TH\DocTest\Subscriber\TestSetup;
use TH\DocTest\TestCase\Example;

/** the EndOfTest hook runs inside the eval'd example code (in a finally) */
final class EndOfTestHookTest extends TestCase
{
    protected function setUp(): void
    {
        EndOfTestSetup::$observations = [];
        EndOfTestSetup::$probe = null;
        \TH\DocTest\Setup\EndOfTestHooks::$hooks = [];
    }

    public function testRunsInsideTheExampleScopeWhileItsVariablesAreAlive(): void
    {
        $example = new Example(<<<'PHP'
            $answer = new \stdClass();
            \TH\DocTest\Tests\Subscriber\EndOfTestSetup::$probe = \WeakReference::create($answer);
            PHP, self::location());

        $this->runExample($example);

        static::assertSame(['example variable still alive'], EndOfTestSetup::$observations);
    }

    public function testRunsOnAbruptExampleEnding(): void
    {
        // a `return` statement would skip a plain appended call; the finally
        // wrapping guarantees the hook still runs
        $this->runExample(new Example('return 42;', self::location()));

        static::assertCount(1, EndOfTestSetup::$observations);
    }

    public function testRunsOnFailingExampleWithoutMaskingTheFailure(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new TestSetup());
        $dispatcher->addSubscriber(new TestExecutor());

        $example = new Example('throw new \LogicException("boom");', self::location());

        $dispatcher->dispatch(new Event\BeforeTest($example));

        try {
            $dispatcher->dispatch(new Event\ExecuteTest($example));
            static::fail('the example exception must propagate through the finally');
        } catch (\LogicException $e) {
            static::assertSame('boom', $e->getMessage());
        }

        static::assertCount(1, EndOfTestSetup::$observations);
    }

    public function testRunsOncePerExample(): void
    {
        $example = new Example('$answer = 42;', self::location());

        $this->runExample($example);
        $this->runExample($example);

        static::assertCount(2, EndOfTestSetup::$observations);
    }

    private function runExample(Example $example): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new TestSetup());
        $dispatcher->addSubscriber(new TestExecutor());

        $dispatcher->dispatch(new Event\BeforeTest($example));
        $dispatcher->dispatch(new Event\ExecuteTest($example));
        $dispatcher->dispatch(new Event\AfterTest($example));
    }

    private static function location(): CodeLocation
    {
        $fname = 'fixture_' . \uniqid();

        $code = <<<PHP
            #[\TH\DocTest\Attributes\ExamplesSetup(new \TH\DocTest\Tests\Subscriber\EndOfTestSetup())]
            function {$fname}(): void {}

            return new \ReflectionFunction('TH\\DocTest\\Tests\\Subscriber\\{$fname}');
            PHP;

        /** @var \ReflectionFunction|false $source */
        $source = eval("namespace TH\\DocTest\\Tests\\Subscriber;\n{$code}");
        \assert($source instanceof \ReflectionFunction, description: 'eval must return a ReflectionFunction');

        return CodeLocation::fromReflection($source, (string) $source->getDocComment());
    }
}
