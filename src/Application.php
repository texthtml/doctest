<?php declare(strict_types=1);

namespace TH\DocTest;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\SingleCommandApplication;

#[AsCommand("doctest")]
final class Application extends SingleCommandApplication
{
    // phpcs:disable SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    protected function configure(): void
    {
        $this
            ->setVersion("1.0.0")
            ->setHelp("Test code examples found in comments")
            ->addArgument(
                "paths",
                Input\InputArgument::IS_ARRAY,
                "Folders and files to look for PHP code examples in",
                default: ["src"],
            )
            ->addOption("bail", "b", Input\InputOption::VALUE_NEGATABLE, "Stop after the first failure", default: false)
            ->addOption(
                "filter",
                "f",
                Input\InputOption::VALUE_REQUIRED,
                "Ony run code blocks whose names match the filter",
                default: "",
            )
            ->addOption(
                "languages",
                "l",
                Input\InputOption::VALUE_REQUIRED | Input\InputOption::VALUE_IS_ARRAY,
                "Ony run code blocks whose language match." . PHP_EOL
                    . "(\"*\" to match any language, \"\" to match unspecified language)",
                default: ["php"],
            );
    }

    protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
    {
        $testSuite = TestSuite::fromPaths(
            $input->getArgument("paths"),
            $input->getOption("filter"),
            $this->getLanguages($input),
        );

        $testSuite->addSubscriber(new Subscriber\ProgressBar($input, $output));
        $testSuite->addSubscriber(new Subscriber\Progress($input, $output));
        $testSuite->addSubscriber(new Subscriber\TestSetup());
        $testSuite->addSubscriber(new Subscriber\Summary($input, $output));
        $testSuite->addSubscriber(new Subscriber\TestExecutor());

        return $testSuite->run($input->getOption("bail") ?? false)
            ? Command::SUCCESS
            : Command::FAILURE;
    }

    /**
     * @return list<string>|null
     */
    private function getLanguages(Input\InputInterface $input): ?array
    {
        $languages = [];

        foreach ($input->getOption("languages") as $lang) {
            if ($lang === '*') {
                $languages = null;

                break;
            }

            $languages[] = $lang;
        }

        return $languages;
    }
}
