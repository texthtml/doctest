<?php

declare(strict_types=1);

namespace TH\DocTest\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidOptionException;
use TH\DocTest\Check;
use TH\DocTest\Mago\Command;

final class CheckTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, list<Check>, list<Command>}>
     */
    public static function options(): iterable
    {
        yield 'default' => [['run'], [Check::Run], []];
        yield 'mago only' => [
            ['lint,analyze'],
            [Check::Lint,    Check::Analyze],
            [Command::Lint,  Command::Analyze],
        ];
        yield 'repeated' => [
            ['analyze',        'run'],
            [Check::Analyze,   Check::Run],
            [Command::Analyze],
        ];
        yield 'spaces & duplicates' => [
            ['lint, run',   'lint'],
            [Check::Lint,   Check::Run],
            [Command::Lint],
        ];
    }

    /**
     * @param list<string> $option
     * @param list<Check> $checks
     * @param list<Command> $magoCommands
     */
    #[DataProvider('options')]
    public function testParsesTheCheckOption(array $option, array $checks, array $magoCommands): void
    {
        static::assertSame($checks, Check::fromOption($option));
        static::assertSame($magoCommands, Check::magoCommands($checks));
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'unknown check' => [['run,nope']];
        yield 'empty' => [[',']];
        yield 'none' => [[]];
    }

    /**
     * @param list<string> $option
     */
    #[DataProvider('invalidOptions')]
    public function testRejectsInvalidChecks(array $option): void
    {
        $this->expectException(InvalidOptionException::class);

        Check::fromOption($option);
    }
}
