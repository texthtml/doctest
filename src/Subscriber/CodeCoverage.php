<?php

declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use SebastianBergmann\CodeCoverage\CodeCoverage as PhpCodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Exception as CodeCoverageException;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\Clover;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;
use TH\DocTest\Event;

/**
 * Collects code coverage of the executed examples and writes a Clover XML report.
 *
 * Examples run in-process (`eval()`), so the coverage driver sees every line of
 * the analyzed codebase they touch — collecting it makes "docs as tests" count
 * toward code coverage. Collection is bracketed around the whole suite run:
 * per-example attribution would double the overhead for little benefit.
 */
final class CodeCoverage implements EventSubscriberInterface
{
    private static ?PhpCodeCoverage $coverage = null;

    /**
     * @param non-empty-string $filename
     * @param list<string>     $paths paths the examples' code lives in: used as the
     *                            coverage filter so runtime noise is not collected
     */
    public function __construct(
        private readonly array $paths,
        private readonly string $filename,
    ) {}

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Event\BeforeTestSuite::class => 'start',
            Event\AfterTestSuite::class => 'write',
        ];
    }

    public function start(): void
    {
        $filter = $this->filter();

        // pcov's collection scope is PHP_INI_SYSTEM-only: it cannot be widened at
        // runtime, and when it excludes the analyzed paths the report silently
        // shows 0% — refuse to emit misleading numbers instead.
        // ini_get returns '' when pcov.directory is unset, in which case pcov
        // auto-detects the vendor directory of the entry script's cwd (see
        // krakjoe/pcov: defaults to ./src).
        if (\function_exists('pcov\start')) {
            $cwd = \getcwd();

            if ($cwd === false) {
                throw new \RuntimeException('could not determine current working directory');
            }

            $configured = \ini_get('pcov.directory');
            $directory = $configured === false || $configured === '' ? $cwd . '/src' : $configured;

            if (!$this->covers($filter, $directory)) {
                throw new \RuntimeException(
                    "pcov.directory ({$directory}) does not include the analyzed paths,"
                    . ' so no coverage of them can be collected.'
                    . ' Restart PHP with -d pcov.directory=<common parent of the analyzed paths>',
                );
            }
        }

        try {
            $driver = (new Selector())->forLineCoverage($filter);
        } catch (CodeCoverageException $exception) {
            throw new \RuntimeException(
                'No code coverage driver available: install & enable pcov (recommended) or xdebug',
                previous: $exception,
            );
        }

        $coverage = new PhpCodeCoverage($driver, $filter);

        $coverage->start('doctest');

        self::$coverage = $coverage;
    }

    public function write(Event\AfterTestSuite $event): void
    {
        $coverage = self::$coverage ?? throw new \LogicException('Code coverage was never started');

        // no per-test status metadata: collecting driver-level data is enough for
        // the aggregate report, and keeps us decoupled from php-code-coverage's
        // TestStatus API, which moved between major versions
        $coverage->stop(true);

        try {
            (new Clover())->process($coverage, $this->filename);
        } catch (CodeCoverageException $exception) {
            throw new \RuntimeException("Could not write code coverage to {$this->filename}", previous: $exception);
        } finally {
            self::$coverage = null;
        }
    }

    /**
     * @return Filter filter restricted to the PHP files of the analyzed paths:
     *                pcov only reports for files in the filter, so without this
     *                nothing meaningful would be collected
     */
    private function filter(): Filter
    {
        $filter = new Filter();

        foreach ($this->paths as $path) {
            if (\is_file($path)) {
                $filter->includeFile($path);

                continue;
            }

            $files = (new Finder())
                ->in($path)
                ->files()
                ->name('*.php')
                ->sortByName();

            foreach ($files as $file) {
                $filter->includeFile($file->getPathname());
            }
        }

        return $filter;
    }

    private function covers(Filter $filter, string $directory): bool
    {
        foreach ($filter->files() as $file) {
            if (\str_starts_with($file, $directory . '/') || $file === $directory) {
                return true;
            }
        }

        return false;
    }
}
