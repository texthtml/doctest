<?php declare(strict_types=1);

namespace TH\DocTest\Subscriber\TestExecutor;

use Webmozart\Assert\Assert;

final class TestCase
{
    public function __construct(public readonly string $code) {}

    /**
     * execute an example and return its output
     */
    public function eval(): string
    {
        \ob_start();

        try {
            eval($this->code);

            $output = \ob_get_clean();
            \assert(\is_string($output), "example messed up with output buffers");

            return $output;
        } catch (\Throwable $th) {
            \ob_get_clean();

            throw $th;
        }
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
