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

/** the CustomAssertions hook routes examples' self::assert* calls through setups */
final class CustomAssertionsTest extends TestCase
{
    protected function setUp(): void
    {
        CustomAssertionSetup::$calls = [];
        \TH\DocTest\Setup\CustomAssertionHandlers::$handlers = [];
        \TH\DocTest\Setup\EndOfTestHooks::$hooks = [];
    }

    public function testHandlerOverridesMatchingAssertionsAndFallsThroughOthers(): void
    {
        $example = new Example(<<<'PHP'
            self::assertResult(a: 1, b: 1);
            self::assertTrue(true);
            PHP, self::location('CustomAssertionSetup'));

        $this->runExample($example);

        // `Result` was overridden (no fall-through), `True` wrapped the default dispatch
        static::assertSame(['handler: result', 'handler: true', 'handler: post-default'], CustomAssertionSetup::$calls);
    }

    public function testOverriddenFailingAssertionFailsTheExample(): void
    {
        $example = new Example('self::assertResult(1, 2);', self::location('CustomAssertionSetup'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new TestSetup());
        $dispatcher->addSubscriber(new TestExecutor());

        $dispatcher->dispatch(new Event\BeforeTest($example));

        try {
            $dispatcher->dispatch(new Event\ExecuteTest($example));
            static::fail('the overridden assertion must fail the example');
        } catch (\LogicException $e) {
            static::assertSame('assertResult failed', $e->getMessage());
        }
    }

    public function testHandlerCanWrapTheDefaultDispatch(): void
    {
        $example = new Example('self::assertTrue(true);', self::location('CustomAssertionSetup'));

        $this->runExample($example);

        // handler pre-logged, default dispatch ran (true didn't throw), handler post-logged
        static::assertSame(['handler: true', 'handler: post-default'], CustomAssertionSetup::$calls);
    }

    public function testUnknownAssertionStillFailsThroughTheDefaultDispatch(): void
    {
        $example = new Example('self::assertNoSuchWebmozartAssertion(1);', self::location('CustomAssertionSetup'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new TestSetup());
        $dispatcher->addSubscriber(new TestExecutor());

        $dispatcher->dispatch(new Event\BeforeTest($example));

        try {
            $dispatcher->dispatch(new Event\ExecuteTest($example));
            static::fail('unknown assertions must still fail the example');
        } catch (\Error $e) { // @mago-ignore analysis:avoid-catching-error (the native undefined-method Error IS the expected failure)
            static::assertSame(
                'Call to undefined method Webmozart\Assert\Assert::noSuchWebmozartAssertion()',
                $e->getMessage(),
            );
        }
    }

    public function testRegistryIsClearedBetweenExamples(): void
    {
        $example = new Example('self::assertResult(1, 1);', self::location('CustomAssertionSetup'));
        $this->runExample($example);

        // second example with no CustomAssertions setup: the previous one's
        // handlers are gone, so assertResult hits the default dispatch
        $bare = new Example('self::assertResult(1, 2);', self::location('HookedSetup'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new TestSetup());
        $dispatcher->addSubscriber(new TestExecutor());

        $dispatcher->dispatch(new Event\BeforeTest($bare));

        try {
            $dispatcher->dispatch(new Event\ExecuteTest($bare));
            static::fail('without the registered handler, assertResult must hit the default dispatch');
        } catch (\Error $e) { // @mago-ignore analysis:avoid-catching-error (the native undefined-method Error IS the expected failure)
            static::assertSame('Call to undefined method Webmozart\Assert\Assert::result()', $e->getMessage());
        }

        static::assertSame(['handler: result'], CustomAssertionSetup::$calls);
    }

    public function testHandlerCanRewriteTheCallForTheRestOfTheChain(): void
    {
        // the rewrite setup aliases Zero to Same(0, …): a downstream handler must
        // observe the rewritten call, and the default dispatch must receive it too
        $example = new Example(<<<'PHP'
            self::assertZero(42);
            PHP, self::location('CustomAssertionSetup'));

        try {
            $this->runExample($example);
            static::fail('the rewritten call must reach the default dispatch');
        } catch (\Webmozart\Assert\InvalidArgumentException $e) {
            static::assertSame('Expected a value identical to 42. Got: 0', $e->getMessage());
        }

        static::assertSame(['handler: zero', 'rewrite: zero → same(0, 42)'], CustomAssertionSetup::$calls);
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

    private static function location(string $setup): CodeLocation
    {
        $fname =
            'fixture_' . \uniqid() . '_' . (string) \preg_replace(pattern: '/\W+/', replacement: '', subject: $setup);
        $class = "TH\\DocTest\\Tests\\Subscriber\\{$setup}";

        $code = <<<PHP
            #[\TH\DocTest\Attributes\ExamplesSetup(new \\{$class}())]
            function {$fname}(): void {}

            return new \ReflectionFunction('TH\\DocTest\\Tests\\Subscriber\\{$fname}');
            PHP;

        /** @var \ReflectionFunction|false $source */
        $source = eval("namespace TH\\DocTest\\Tests\\Subscriber;\n{$code}");
        \assert($source instanceof \ReflectionFunction, description: 'eval must return a ReflectionFunction');

        return CodeLocation::fromReflection($source, (string) $source->getDocComment());
    }
}
