<?php declare(strict_types=1);

namespace TH\DocTest\Tests\Iterator;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TH\DocTest\Iterator\AllExamples;
use TH\DocTest\Iterator\Comments;
use TH\DocTest\Location;

final class AllExamplesTest extends TestCase
{
    /**
     * @return \Traversable<string,array{comment:string, expectedExamples:list<string>}>
     * phpcs:disable SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
     */
    public static function commentsProvider(): \Traversable
    {
        yield "Empty comment" => [
            "comment" => "\/** */",
            "expectedExamples" => [],
        ];

        yield "Comment with a code bloc" => [
            "comment" => "This is a comment",
            "expectedExamples" => [],
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
            "expectedExamples" => [
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
            "expectedExamples" => [],
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
            "expectedExamples" => [
                <<<'JS'
                console.log("Hello World!")
                JS,
            ],
            "acceptedLanguages" => [""],
        ];

        yield "Comment with a non specified code bloc, when not accepted" => [
            "comment" => $comment,
            "expectedExamples" => [],
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
            "expectedExamples" => [
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
            "expectedExamples" => [
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
            "expectedExamples" => [
                <<<'PHP'
                echo "Hello World!";
                PHP,
            ],
        ];
    }

    /**
     * @param list<string> $expectedExamples
     * @param list<string>|null $acceptedLanguages
     */
    #[DataProvider("commentsProvider")]
    public function testFindingCodeBlocInComments(
        string $comment,
        array $expectedExamples,
        ?array $acceptedLanguages = ["php"],
    ): void {
        $examples = new AllExamples(
            self::comments($comment),
            $acceptedLanguages,
        );

        $count = 0;

        foreach ($examples as $example) {
            Assert::assertArrayHasKey($count, $expectedExamples, "Example #$count was not expected");
            Assert::assertEquals(
                $expectedExamples[$count],
                $example->code,
                "Example #$count doesn't match the expected one",
            );

            $count++;
        }

        $expectedExamplesLeft = \count($expectedExamples) - $count;

        Assert::assertSame($expectedExamplesLeft, 0, "$expectedExamplesLeft more example(s) expected");
    }

    private static function comments(string ...$comments): Comments
    {
        return new class ($comments) implements Comments {
            public function __construct(
                /** @var array<string> */
                private readonly array $comments,
            ) {}

            public static function location(string $comment): Location
            {
                static $thisClass = new \ReflectionClass(self::class);

                return Location::fromReflection($thisClass, $comment);
            }

            public function getIterator(): \Traversable
            {
                foreach ($this->comments as $comment) {
                    yield self::location($comment) => $comment;
                }
            }
        };
    }
}
