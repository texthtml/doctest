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
     */
    public function __invoke(
        Input\InputInterface $input,
        Output\OutputInterface $output,
        #[Argument('Folders and files to look for PHP code examples in')]
        array $paths = ['src'],
        // nullable so Symfony derives VALUE_NEGATABLE and --no-bail exists
        #[Option('Stop after the first failure', shortcut: 'b')]
        ?bool $bail = null,
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
    ): int {
        // Symfony resolves the attribued parameters to the declared types, but the
        // analyzer only sees `array` — map back to list<string> at the boundary
        $paths = self::listOfStrings($paths);
        $languages = self::listOfStrings($languages);

        $testSuite = TestSuite::fromPaths(
            $paths,
            $filter,
            $this->acceptedLanguages($languages),
            new ConsoleLogger($output, [LogLevel::WARNING => Output\OutputInterface::VERBOSITY_NORMAL]),
        );

        $testSuite->addSubscriber(new Subscriber\ProgressBar($input, $output));
        $testSuite->addSubscriber(new Subscriber\Progress($input, $output));
        $testSuite->addSubscriber(new Subscriber\TestSetup());
        $testSuite->addSubscriber(new Subscriber\Summary($input, $output));
        $testSuite->addSubscriber(new Subscriber\TestExecutor());

        if ($junit !== null) {
            $testSuite->addSubscriber(new Subscriber\JunitOutput($junit));
        }

        return $testSuite->run($bail ?? false)->isSuccess() ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param array<mixed> $value
     *
     * @return list<string>
     */
    private static function listOfStrings(array $value): array
    {
        $values = [];

        foreach ($value as $item) {
            /** @var mixed $item */
            if (\is_string($item)) {
                $values[] = $item;
            }
        }

        return $values;
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
