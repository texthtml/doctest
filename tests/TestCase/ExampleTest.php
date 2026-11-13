<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\TestCase;

use PHPUnit\Framework\TestCase;
use TH\DocTest\TestCase\Example;

final class ExampleTest extends TestCase
{
    public function testUnknownAssertMethodWithPrefix(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to undefined method Webmozart\Assert\Assert::NoSuchMethod()');

        // __callStatic proxies to Webmozart Assert; a bogus method name is expected to throw
        // @mago-expect analysis:non-documented-method
        Example::assertNoSuchMethod();
    }

    public function testUnknownAssertMethodWithoutPrefix(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to undefined method Webmozart\Assert\Assert::noSuchMethod()');

        // __callStatic proxies to Webmozart Assert; a bogus method name is expected to throw
        // @mago-expect analysis:non-documented-method
        Example::noSuchMethod();
    }
}
