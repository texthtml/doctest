<?php

declare(strict_types=1);

namespace TH\DocTest;

use Symfony\Component\Console\Exception\InvalidOptionException;

/**
 * what doctest does with the examples (`--check`): run them, and/or check them
 * with Mago; an example fails when any of the checks fails
 */
enum Check: string
{
    case Run = 'run';
    case Lint = 'lint';
    case Analyze = 'analyze';

    /**
     * @param list<string> $option the `--check` values, each one possibly comma-separated
     *
     * @return non-empty-list<self>
     *
     * @throws InvalidOptionException
     */
    public static function fromOption(array $option): array
    {
        $checks = [];

        foreach ($option as $value) {
            foreach (\explode(separator: ',', string: $value) as $name) {
                $check = self::tryFrom(\trim($name)) ?? throw new InvalidOptionException(
                    "Invalid --check value \"{$value}\": expected \"run\", \"lint\" and/or \"analyze\"",
                );

                $checks[$check->value] = $check;
            }
        }

        if ($checks === []) {
            throw new InvalidOptionException('--check needs at least one of "run", "lint" or "analyze"');
        }

        return \array_values($checks);
    }

    /**
     * @param list<self> $checks
     *
     * @return list<Mago\Command> the Mago commands among the checks
     */
    public static function magoCommands(array $checks): array
    {
        $commands = [];

        foreach ($checks as $check) {
            $command = match ($check) {
                self::Run => null,
                self::Lint => Mago\Command::Lint,
                self::Analyze => Mago\Command::Analyze,
            };

            if ($command !== null) {
                $commands[] = $command;
            }
        }

        return $commands;
    }
}
