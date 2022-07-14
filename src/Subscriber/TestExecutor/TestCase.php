<?php declare(strict_types=1);

namespace TH\DocTest\Subscriber\TestExecutor;

use Webmozart\Assert\Assert;

final class TestCase
{
    public function __construct(public readonly string $code) {}

    /**
     * execute an example and return its output
     */
    public function eval(): void
    {
        eval($this->code);
    }

    /**
     * @param array<mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): void
    {
        $name = \preg_replace("/^assert/", "", $name);

        // @phpstan-ignore-next-line Variable static method call on Webmozart\Assert\Assert.
        Assert::$name(...$arguments);
    }
}
