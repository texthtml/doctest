<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Mago;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TH\DocTest\Iterator\AllTests;
use TH\DocTest\Iterator\SourceComments;
use TH\DocTest\Iterator\StaticComments;
use TH\DocTest\Mago\Cli;
use TH\DocTest\Mago\Runner;
use TH\DocTest\TestCase\Example;

/**
 * runs the project's mago (vendor/bin/mago) on tests/data/mago/examples.php
 */
final class RunnerTest extends TestCase
{
    private const FIXTURE = 'tests/data/mago/examples.php';

    /**
     * @return iterable<string, array{bool}>
     */
    public static function backends(): iterable
    {
        yield 'static scan' => [true];
        yield 'reflection' => [false];
    }

    #[DataProvider('backends')]
    public function testIssuesAreReportedAtTheirLineInTheDocumentedFile(bool $staticScan): void
    {
        static::assertSame(
            [
                // `@prints` & `@throws` annotations, `self::assert*()`: no issue
                'clean' => [],
                'wrong_argument' => [
                    self::FIXTURE
                        . ":29: error[invalid-argument] Invalid argument type for argument #1 of `TH\DocTest\Tests\Data\Mago\clean`: expected `int`, but found `string('two')`.",
                ],
                'unknown_assertion' => [
                    self::FIXTURE
                        . ':36: error[non-existent-method] Method `assertNope` does not exist on type `self`.',
                ],
                // an example with setups may use custom assertions
                'custom_assertion' => [],
                // `self::assertString()` narrows like `Assert::string()`, hoisted `use` resolve
                'narrowing' => [],
                'not_narrowed' => [
                    self::FIXTURE
                        . ':63: error[possibly-invalid-argument] Possible argument type mismatch for argument #1 of `strlen`: expected `string`, but possibly received `int(1)|string(\'one\')`.',
                ],
                // reported where the wrapper closes the example, mapped back inside it
                'parse_error' => [
                    self::FIXTURE . ':71: error[parse] Parse error encountered during parsing: Unexpected end of file',
                    self::FIXTURE
                        . ':71: error[parse] Parse error encountered during parsing: Expected one of `RightBrace` before end of file',
                ],
            ],
            self::check($staticScan),
        );
    }

    public function testNoExamplesNoMagoRun(): void
    {
        $runner = new Runner(new Cli(['false'], self::workspace()));

        static::assertCount(0, $runner->check([]));
    }

    public function testExportDirectoryIsRemoved(): void
    {
        self::check(staticScan: true);

        static::assertSame([], \glob(self::workspace() . '/.doctest-mago-*', \GLOB_ONLYDIR));
    }

    /**
     * @return array<string, list<string>> issues by documented function short name
     */
    private static function check(bool $staticScan): array
    {
        $comments = $staticScan
            ? StaticComments::fromPaths([self::FIXTURE], new NullLogger())
            : SourceComments::fromPaths([self::FIXTURE], new NullLogger());

        /** @var list<Example> $examples */
        $examples = \iterator_to_array(AllTests::from($comments, ['php']), preserve_keys: false);
        $issues = (new Runner(Cli::fromWorkspace(self::workspace())))->check($examples);
        $byFunction = [];

        foreach ($examples as $example) {
            $name = \preg_replace('/^.*\\\\/', replacement: '', subject: $example->location()->name);
            $byFunction[(string) $name] = \array_map(\strval(...), $issues[$example] ?? []);
        }

        return $byFunction;
    }

    /**
     * @return non-empty-string
     */
    private static function workspace(): string
    {
        $cwd = \getcwd();

        if ($cwd === false) {
            throw new \RuntimeException('getcwd failed');
        }

        return $cwd;
    }
}
