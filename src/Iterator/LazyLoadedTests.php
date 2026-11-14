<?php

declare(strict_types=1);

namespace TH\DocTest\Iterator;

use Psr\Log\LoggerInterface;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\Location\SymbolSource;
use TH\DocTest\TestCase;

/**
 * Lazily includes the file a scanned symbol lives in before its examples run:
 * static discovery loads nothing, so code under test is only executed (and
 * only risks side effects) when an example actually references it.
 */
final class LazyLoadedTests implements Tests
{
    /**
     * @var array<string,bool>
     */
    private static array $included = [];

    /**
     * @param \Traversable<TestCase> $tests tests ordered by file, so each file's
     *                                      examples arrive together and its
     *                                      file can be included just before
     */
    public function __construct(
        private readonly \Traversable $tests,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return \Traversable<TestCase>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->tests as $test) {
            $location = $test->location();

            if ($location instanceof CodeLocation && $location->source instanceof SymbolSource) {
                self::includeOnce($location->source->path, $this->logger);
            }

            yield $test;
        }
    }

    private static function includeOnce(string $path, LoggerInterface $logger): void
    {
        if ($path === '' || \array_key_exists($path, self::$included)) {
            return;
        }

        self::$included[$path] = true;

        try {
            include_once $path;
        } catch (\Throwable $th) {
            // whatever the file does at load time is reported as the first
            // example's failure rather than killing the whole run
            $logger->error('Failed to include {path}: {message}', [
                'path' => $path,
                'message' => $th->getMessage(),
            ]);
        }
    }
}
