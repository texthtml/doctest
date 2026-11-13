<?php

declare(strict_types=1);

namespace TH\DocTest\Iterator;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionException;
use TH\DocTest\Location\FileLocation;
use TH\DocTest\TestCase;

/**
 * @implements \IteratorAggregate<int,\ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction|TestCase\SourceError>
 */
final class CommentReflectionSources implements \IteratorAggregate
{
    /**
     * @param array<string> $paths paths to files and folders to look for PHP comments sources in
     */
    public function __construct(
        private array $paths,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * @return \Traversable<int, \ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction|TestCase\SourceError>
     */
    public function getIterator(): \Traversable
    {
        $files = [];

        foreach (new Files($this->paths, $this->logger) as $file) {
            $originalPath = $file->getPathname();

            $path = self::resolveIncludePath($originalPath);

            if ($path instanceof TestCase\SourceError) {
                yield $path;

                continue;
            }

            $error = self::load($path, $originalPath);
            $files[] = $path;
            include_once $path;

            if ($error !== null) {
                yield $error;
            }
        }

        yield from $this->declaredInFiles($this->interfaces(), $files);
        yield from $this->declaredInFiles($this->classes(), $files);
        yield from $this->declaredInFiles($this->functions(), $files);
    }

    private function load(string $path, string $originalPath): ?TestCase\SourceError
    {
        try {
            \ob_start();
            include_once $path;

            return null;
        } catch (\Throwable $th) {
            return new TestCase\SourceError(FileLocation::fromPath($originalPath), $th);
        } finally {
            \ob_end_clean();
        }
    }

    private function resolveIncludePath(string $path): string|TestCase\SourceError
    {
        $resolvedPath = \stream_resolve_include_path($path);

        if ($resolvedPath === false) {
            return new TestCase\SourceError(
                FileLocation::fromPath($path),
                new \RuntimeException("Could not resolve path to a file to include: {$path}"),
            );
        }

        return $resolvedPath;
    }

    /**
     * @param \Traversable<int, \ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction> $sources
     * @param array<string> $files
     * @return \Traversable<int, \ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction>
     */
    private function declaredInFiles(\Traversable $sources, array $files): \Traversable
    {
        foreach ($sources as $commentReflectionSource) {
            if (!\in_array(needle: $commentReflectionSource->getFileName(), haystack: $files, strict: true)) {
                continue;
            }

            yield $commentReflectionSource;
        }
    }

    /**
     * @return \Traversable<\ReflectionClass<object>|\ReflectionMethod>
     */
    private function classes(): \Traversable
    {
        foreach (\get_declared_classes() as $className) {
            yield $rc = new \ReflectionClass($className);

            foreach ($rc->getMethods() as $rm) {
                yield $rm;
            }
        }
    }

    /**
     * @return \Traversable<\ReflectionClass<object>|\ReflectionMethod>
     */
    private function interfaces(): \Traversable
    {
        foreach (\get_declared_interfaces() as $className) {
            yield $rc = new \ReflectionClass($className);

            foreach ($rc->getMethods() as $rm) {
                yield $rm;
            }
        }
    }

    /**
     * @return \Traversable<\ReflectionFunction>
     */
    private function functions(): \Traversable
    {
        foreach (\get_defined_functions()['user'] as $functionName) {
            try {
                yield new \ReflectionFunction($functionName);
            } catch (ReflectionException $th) {
                // get_defined_functions() can race with the autoloader: the function may no longer exist
                $this->logger->warning('Skipped unreflectable function {function}: {message}', [
                    'function' => $functionName,
                    'message' => $th->getMessage(),
                ]);
            }
        }
    }
}
