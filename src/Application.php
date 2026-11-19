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
use TH\Maybe;

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
     * @param list<string> $check "run", "lint" and/or "analyze", each possibly comma-separated
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
        #[Option('What to do with the examples: "run" them, check them with Mago ("lint", "analyze"),'
        . PHP_EOL
        . 'or any combination (comma-separated or repeated, e.g. --check=run,analyze)', suggestedValues: [
            'run',
            'lint',
            'analyze',
        ])]
        array $check = ['run'],
        #[Option('Mago executable for the lint & analyze checks (default: vendor/bin/mago, or mago from the PATH)')]
        ?string $magoBinary = null,
    ): int {
        $logger = new ConsoleLogger($output, [LogLevel::WARNING => Output\OutputInterface::VERBOSITY_NORMAL]);
        $acceptedLanguages = $this->acceptedLanguages($languages);
        $checks = Check::fromOption($check);
        $run = \in_array(Check::Run, $checks, strict: true);
        $magoCommands = Check::magoCommands($checks);

        $coverageClover = $coverageClover === '' ? null : $coverageClover;

        if (!$run && $coverageClover !== null) {
            $logger->error('--coverage-clover needs the "run" check: the examples are not executed otherwise');

            return Command::INVALID;
        }

        $tests = TestSuite::discover(
            $paths,
            $filter,
            $acceptedLanguages,
            $logger,
            $staticScan,
            lazyLoad: $magoCommands === [],
        );
        $magoChecker = null;

        if ($magoCommands !== []) {
            [$tests, $magoChecker] = Subscriber\MagoChecker::checkTests(
                $tests,
                new Mago\Runner(Mago\Cli::fromWorkingDirectory($magoBinary), $magoCommands),
                lazyLoadLogger: $run && $staticScan ? $logger : null,
            );
        }

        $testSuite = new TestSuite($tests);

        $testSuite->addSubscriber(new Subscriber\ProgressBar($input, $output));
        $testSuite->addSubscriber(new Subscriber\Progress($input, $output));

        if ($run) {
            // setups are only meaningful when the examples run
            $testSuite->addSubscriber(new Subscriber\TestSetup($logger));
            $testSuite->addSubscriber(new Subscriber\TestExecutor());
        }

        if ($magoChecker !== null) {
            $testSuite->addSubscriber($magoChecker);
        }

        $testSuite->addSubscriber(new Subscriber\Summary($input, $output));

        if ($junit !== null) {
            $testSuite->addSubscriber(new Subscriber\JunitOutput($junit));
        }

        if ($coverageClover !== null) {
            $testSuite->addSubscriber(new Subscriber\CodeCoverage($paths, $coverageClover));
        }

        return $testSuite->run($bail)->isSuccess() ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param list<string> $languages "*" to match any language, "" to match unspecified language
     *
     * @return Maybe\Option<list<string>> none to match any language
     */
    private function acceptedLanguages(array $languages): Maybe\Option
    {
        $accepted = [];

        foreach ($languages as $lang) {
            if ($lang === '*') {
                return Maybe\Option\none();
            }

            $accepted[] = $lang;
        }

        return Maybe\Option\some($accepted);
    }
}
