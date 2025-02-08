<?php declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use TH\DocTest\Event\ExecuteTest;
use TH\DocTest\Example;
use TH\DocTest\Location;
use TH\DocTest\Subscriber\TestExecutor;
use TH\Maybe\Option;

/**
 * @phpstan-type Failure array{class:class-string<\Throwable>,message:string}
 */
final class TestExecutorTest extends TestCase
{
    private TestExecutor $testExecutor;

    /**
     * @return \Traversable<string,array{0:Example,1:Failure|null}>
     * @throws \Symfony\Component\Finder\Exception\DirectoryNotFoundException
     */
    public static function codeBlocsProvider(): \Traversable
    {
        $index = 1;

        foreach (self::codeBlocs() as $path => $example) {
            ["code" => $code, "failure" => $failure] = self::loadExample($example);

            $location = new Location(
                new ReflectionClass(self::class),
                self::class,
                path: Option\some($path),
                startLine: Option\some(1),
                endLine: Option\none(),
                index: $index++,
            );

            $realpath = \realpath($path);
            \assert(\is_string($realpath), "Getting realpath of $path failed");

            yield $realpath => [new Example($code, $location), $failure];
        }
    }

    public function setUp(): void
    {
        $this->testExecutor = new TestExecutor();
    }

    /**
     * @param Option<Failure> $failure
     * @throws \InvalidArgumentException
     */
    #[DataProvider('codeBlocsProvider')]
    public function testCodeBlocs(Example $example, Option $failure): void
    {
        $failure->inspect(function (array $failure): void {
            $this->expectException($failure["class"]);
            $message = \preg_quote($failure["message"], "/");
            $this->expectExceptionMessageMatches("/^$message$/");
        });

        $this->testExecutor->execute(new ExecuteTest($example));

        self::assertTrue($failure->isNone());
    }

    /**
     * @return \Traversable<string,\SplFileInfo>
     * @throws \Symfony\Component\Finder\Exception\DirectoryNotFoundException
     */
    private static function codeBlocs(): \Traversable
    {
        return (new Finder())
            ->files()
            ->in(__DIR__ . "/../data/code-blocs")
            ->name("*.php");
    }

    /**
     * @return array{code:string,failure:Option<Failure>}
     */
    private static function loadExample(\SplFileInfo $example): array
    {
        $code = \file_get_contents($example->getPathname());
        \assert(\is_string($code), "Getting content from file {$example->getPathname()} failed");

        $code = \preg_replace("/^<\?php/", "", $code);
        \assert(\is_string($code), "Something wrong happened");

        $failure = self::pregMatch("/^( *)\/\/(?<comment>.*)/", $code)
            ->andThen(
                static fn (array $matches) => self::pregMatch("/(?<class>[^ ]+) (?<message>.+)/", $matches["comment"]),
            )
            ->map(static function (array $matches) {
                \assert(
                    \is_subclass_of($matches["class"], \Throwable::class),
                    "{$matches["class"]} is not a Throwable",
                );

                return ["class" => $matches["class"], "message" => $matches["message"]];
            });

        return ["code" => $code, "failure" => $failure];
    }

    /**
     * @return Option<array<string>>
     */
    private static function pregMatch(string $pattern, string $subject): Option
    {
        \preg_match($pattern, $subject, $matches);

        return Option\fromValue($matches, []);
    }
}
