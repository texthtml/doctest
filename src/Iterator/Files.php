<?php

declare(strict_types=1);

namespace TH\DocTest\Iterator;

use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

/**
 * @implements \IteratorAggregate<\SplFileInfo>
 */
final class Files implements \IteratorAggregate
{
    /**
     * @param array<string> $paths paths to PHP files & folders to look for PHP files in
     */
    public function __construct(
        private array $paths,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return \Traversable<\SplFileInfo>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->iteratePaths() as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            yield $file;
        }
    }

    /**
     * @return \Traversable<\SplFileInfo>
     */
    public function iteratePaths(): \Traversable
    {
        foreach ($this->paths as $path) {
            yield from $this->iterate($path);
        }
    }

    /**
     * @return \Traversable<\SplFileInfo>
     */
    private function iterate(string $pattern): \Traversable
    {
        $paths = \glob($pattern);

        if ($paths === false) {
            return;
        }

        foreach ($paths as $path) {
            yield from $this->iteratePath($path);
        }
    }

    /**
     * @return \Traversable<\SplFileInfo>
     */
    private function iteratePath(string $path): \Traversable
    {
        if (\is_file($path)) {
            yield new \SplFileInfo($path);

            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        // deterministic output requires deterministic file ordering: readdir order is
        // filesystem-dependent, so tests must not depend on it
        $finder = (new Finder())->in($path)->sortByName();

        try {
            yield from $finder;
        } catch (DirectoryNotFoundException $th) {
            // the directory existed at the is_dir() check above: it was removed since, racing the iteration
            $this->logger->warning('Skipped non-readable path {path}: {message}', [
                'path' => $path,
                'message' => $th->getMessage(),
            ]);
        }
    }
}
