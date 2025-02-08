<?php declare(strict_types=1);

namespace TH\DocTest\Tests\Iterator;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TH\DocTest\Iterator\AllTests;
use TH\DocTest\Iterator\Comment;
use TH\DocTest\Iterator\Comments;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\TestCase\Example;

final class AllTestsTest extends TestCase
{
    /**
     * @return \Traversable<string,array{comment:string, expectedTests:list<string>}>
     * phpcs:disable SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
     */
    public static function commentsProvider(): \Traversable
    {
        yield "Empty comment" => [
            "comment" => "\/** */",
            "expectedTests" => [],
        ];

        yield "Comment with a code bloc" => [
            "comment" => "This is a comment",
            "expectedTests" => [],
        ];

        $comment = <<<'COMMENT'
            /**
            * ```php
            * echo "Hello World!";
            * ```
            */
            COMMENT;

        yield "Comment with a PHP code bloc" => [
            "comment" => $comment,
            "expectedTests" => [
                <<<'PHP'
                echo "Hello World!";
                PHP,
            ],
        ];

        $comment = <<<'COMMENT'
            /**
            * ```js
            * console.log("Hello World!")
            * ```
            */
            COMMENT;

        yield "Comment with a JS code bloc" => [
            "comment" => $comment,
            "expectedTests" => [],
        ];

        $comment = <<<'COMMENT'
            /**
            * ```
            * console.log("Hello World!")
            * ```
            */
            COMMENT;

        yield "Comment with a non specified code bloc, when accepted" => [
            "comment" => $comment,
            "expectedTests" => [
                <<<'JS'
                console.log("Hello World!")
                JS,
            ],
            "acceptedLanguages" => [""],
        ];

        yield "Comment with a non specified code bloc, when not accepted" => [
            "comment" => $comment,
            "expectedTests" => [],
        ];

        $comment = <<<'COMMENT'
            /**
            * ```php
            * echo "Hello ";
            * echo "World!";
            * ```
            */
            COMMENT;

        yield "Comment with a multi line PHP code bloc" => [
            "comment" => $comment,
            "expectedTests" => [
                <<<'PHP'
                echo "Hello ";
                echo "World!";
                PHP,
            ],
        ];

        $comment = <<<'COMMENT'
            /**
            * Here is how you do it in PHP:
            *
            * ```php
            * echo "Hello World!";
            * ```
            *
            * ```php
            * echo "Hello ";
            * echo "World!";
            * ```
            */
            COMMENT;

        yield "Comment with multiple PHP code blocs" => [
            "comment" => $comment,
            "expectedTests" => [
                <<<'PHP'
                echo "Hello World!";
                PHP,
                <<<'PHP'
                echo "Hello ";
                echo "World!";
                PHP,
            ],
        ];

        $comment = <<<'COMMENT'
            /**
            * Here is how you do it in JS:
            *
            * ```js
            * console.log("Hello World!")
            * ```
            *
            * And here in PHP:
            *
            * ```php
            * echo "Hello World!";
            * ```
            *
            * ```
            * echo "Hello ";
            * echo "World!";
            * ```
            */
            COMMENT;

        yield "Comment with multiple PHP/JS code blocs" => [
            "comment" => $comment,
            "expectedTests" => [
                <<<'PHP'
                echo "Hello World!";
                PHP,
            ],
        ];
    }

    /**
     * @param list<string> $expectedTests
     * @param list<string>|null $acceptedLanguages
     */
    #[DataProvider("commentsProvider")]
    public function testFindingCodeBlocInComments(
        string $comment,
        array $expectedTests,
        ?array $acceptedLanguages = ["php"],
    ): void {
        $tests = new AllTests(
            self::comments($comment),
            $acceptedLanguages,
        );

        $count = 0;

        foreach ($tests as $test) {
            Assert::assertArrayHasKey($count, $expectedTests, "Test #$count was not expected");
            Assert::assertInstanceOf(Example::class, $test);
            Assert::assertEquals($expectedTests[$count], $test->code, "Test #$count doesn't match the expected one");

            $count++;
        }

        $expectedTestsLeft = \count($expectedTests) - $count;

        Assert::assertSame($expectedTestsLeft, 0, "$expectedTestsLeft more test(s) expected");
    }

    private static function comments(string ...$comments): Comments
    {
        return new class ($comments) implements Comments {
            public function __construct(
                /** @var array<string> */
                private readonly array $comments,
            ) {}

            public static function location(string $comment): CodeLocation
            {
                static $thisClass = new \ReflectionClass(self::class);

                return CodeLocation::fromReflection($thisClass, $comment);
            }

            public function getIterator(): \Traversable
            {
                foreach ($this->comments as $comment) {
                    yield new Comment($comment, self::location($comment));
                }
            }
        };
    }
}
