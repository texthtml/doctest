<?php

declare(strict_types=1);

namespace TH\DocTest;

use Composer\InstalledVersions;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\SingleCommandApplication;

#[AsCommand('doctest', help: 'Test code examples found in comments')]
final class Application extends SingleCommandApplication
{
    public function __construct()
    {
        parent::__construct();

        $this->setVersion(InstalledVersions::getPrettyVersion('texthtml/doctest') ?? 'dev');
    }

    /**
     * the parameters *are* the command definition; folding them into a DTO would just
     * move the verbosity while hiding the help text from the signature
     *
     * @mago-expect lint:excessive-parameter-list
     *
     * @param list<string> $paths
     * @param list<string> $languages "*" to match any language, "" to match unspecified language
     */
    public function __invoke(
        Input\InputInterface $input,
        Output\OutputInterface $output,
        #[Argument('Folders and files to look for PHP code examples in')]
        array $paths = ['src'],
        #[Option('Stop after the first failure', shortcut: 'b')]
        bool $bail = false,
        #[Option('Ony run code blocks whose names match the filter', shortcut: 'f')]
        string $filter = '',
        #[Option(
            'Ony run code blocks whose language match.'
            . PHP_EOL
            . '("*" to match any language, "" to match unspecified language)',
            shortcut: 'l',
        )]
        array $languages = ['php'],
        #[Option('Write test results to a file in JUnit XML format')]
        ?string $junit = null,
        #[Option('Write code coverage of the executed examples to a file in Clover XML format')]
        ?string $coverageClover = null,
        #[Option('Scan files for examples statically (never include the code under test)')]
        bool $staticScan = false,
        #[Option('Check the examples with Mago (lint & analyze) instead of running them')]
        bool $mago = false,
        #[Option('Mago executable to use with --mago (default: vendor/bin/mago, or mago from the PATH)')]
        ?string $magoBinary = null,
    ): int {
        $logger = new ConsoleLogger($output, [LogLevel::WARNING => Output\OutputInterface::VERBOSITY_NORMAL]);
        $acceptedLanguages = $this->acceptedLanguages($languages);

        if ($mago && $coverageClover !== null && $coverageClover !== '') {
            $logger->error('--coverage-clover cannot be used with --mago: examples are not executed');

            return Command::INVALID;
        }

        if ($mago) {
            [$testSuite, $executor] = $this->magoCheckedTestSuite(
                TestSuite::discover($paths, $filter, $acceptedLanguages, $logger, $staticScan, lazyLoad: false),
                $magoBinary,
            );
        } else {
            $executor = new Subscriber\TestExecutor();
            $testSuite = TestSuite::fromPaths($paths, $filter, $acceptedLanguages, $logger, $staticScan);
        }

        $testSuite->addSubscriber(new Subscriber\ProgressBar($input, $output));
        $testSuite->addSubscriber(new Subscriber\Progress($input, $output));

        if (!$mago) {
            // setups are only meaningful when the examples run
            $testSuite->addSubscriber(new Subscriber\TestSetup($logger));
        }

        $testSuite->addSubscriber(new Subscriber\Summary($input, $output));
        $testSuite->addSubscriber($executor);

        if ($junit !== null) {
            $testSuite->addSubscriber(new Subscriber\JunitOutput($junit));
        }

        if ($coverageClover !== null && $coverageClover !== '') {
            $testSuite->addSubscriber(new Subscriber\CodeCoverage($paths, $coverageClover));
        }

        return $testSuite->run($bail)->isSuccess() ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Mago checks all the examples at once, up front: the test suite then only
     * reports, example by example, what it found
     *
     * @return array{TestSuite, Subscriber\MagoChecker}
     */
    private function magoCheckedTestSuite(Iterator\Tests $tests, ?string $magoBinary): array
    {
        $tests = \iterator_to_array($tests, preserve_keys: false);
        $cwd = \getcwd();

        if ($cwd === false) {
            throw new \RuntimeException('getcwd failed');
        }

        $mago = new Mago\Runner(Mago\Cli::fromWorkspace($cwd, $magoBinary));

        return [new TestSuite(new Iterator\ListedTests($tests)), Subscriber\MagoChecker::check($tests, $mago)];
    }

    /**
     * @param list<string> $languages "*" to match any language, "" to match unspecified language
     *
     * @return list<string>|null null to match any language
     */
    private function acceptedLanguages(array $languages): ?array
    {
        $accepted = [];

        foreach ($languages as $lang) {
            if ($lang === '*') {
                return null;
            }

            $accepted[] = $lang;
        }

        return $accepted;
    }
}
