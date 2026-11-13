<?php

declare(strict_types=1);

namespace TH\DocTest;

use Composer\InstalledVersions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\SingleCommandApplication;

#[AsCommand('doctest')]
final class Application extends SingleCommandApplication
{
    protected function configure(): void
    {
        $this
            ->setVersion(InstalledVersions::getPrettyVersion('texthtml/doctest') ?? 'dev')
            ->setHelp('Test code examples found in comments')
            ->addArgument(
                'paths',
                Input\InputArgument::IS_ARRAY,
                'Folders and files to look for PHP code examples in',
                default: ['src'],
            )
            ->addOption('bail', 'b', Input\InputOption::VALUE_NEGATABLE, 'Stop after the first failure', default: false)
            ->addOption(
                'filter',
                'f',
                Input\InputOption::VALUE_REQUIRED,
                'Ony run code blocks whose names match the filter',
                default: '',
            )
            ->addOption(
                'languages',
                'l',
                Input\InputOption::VALUE_REQUIRED | Input\InputOption::VALUE_IS_ARRAY,
                'Ony run code blocks whose language match.'
                . PHP_EOL
                . '("*" to match any language, "" to match unspecified language)',
                default: ['php'],
            )
            ->addOption(
                'junit',
                null,
                Input\InputOption::VALUE_REQUIRED,
                'Write test results to a file in JUnit XML format',
                default: null,
            );
    }

    protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
    {
        $testSuite = TestSuite::fromPaths(
            self::strings($input->getArgument('paths')),
            self::string($input->getOption('filter')),
            $this->getLanguages($input),
        );

        $testSuite->addSubscriber(new Subscriber\ProgressBar($input, $output));
        $testSuite->addSubscriber(new Subscriber\Progress($input, $output));
        $testSuite->addSubscriber(new Subscriber\TestSetup());
        $testSuite->addSubscriber(new Subscriber\Summary($input, $output));
        $testSuite->addSubscriber(new Subscriber\TestExecutor());

        $junit = self::nullableString($input->getOption('junit'));

        if ($junit !== null) {
            $testSuite->addSubscriber(new Subscriber\JunitOutput($junit));
        }

        return $testSuite->run(self::bool($input->getOption('bail')))->isSuccess()
            ? Command::SUCCESS
            : Command::FAILURE;
    }

    private static function string(mixed $value): string
    {
        return self::nullableString($value) ?? '';
    }

    private static function nullableString(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    private static function bool(mixed $value): bool
    {
        return \is_bool($value) ? $value : false;
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        if (!\is_array($value)) {
            $value = $value === null ? [] : [$value];
        }

        $values = [];

        /** @var mixed $item */
        foreach ($value as $item) {
            if (!\is_string($item)) {
                continue;
            }

            $values[] = $item;
        }

        return $values;
    }

    /**
     * @return list<string>|null
     */
    private function getLanguages(Input\InputInterface $input): ?array
    {
        $languages = [];

        foreach (self::strings($input->getOption('languages')) as $lang) {
            if ($lang === '*') {
                return null;
            }

            $languages[] = $lang;
        }

        return $languages;
    }
}
