<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

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
    ) {
    }

    /**
     * @return \Traversable<\SplFileInfo>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->iteratePaths() as $file) {
            if ($file->getExtension() === "php") {
                yield $file;
            }
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
        foreach (self::glob($pattern) as $path) {
            yield from $this->iteratePath($path);
        }
    }

    /**
     * @return \Traversable<string>
     */
    private static function glob(string $pattern): \Traversable
    {
        $paths = \glob($pattern);

        if ($paths === false) {
            return;
        }

        foreach ($paths as $path) {
            yield $path;
        }
    }

    /**
     * @return \Traversable<\SplFileInfo>
     */
    private function iteratePath(string $path): \Traversable
    {
        if (\is_file($path)) {
            yield new \SplFileInfo($path);
        } elseif (\is_dir($path)) {
            $finder = new Finder();

            try {
                $finder->in($path);

                yield from $finder;
            } catch (DirectoryNotFoundException) {
            }
        }
    }
}
