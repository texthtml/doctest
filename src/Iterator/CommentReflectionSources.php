<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use ReflectionException;
use TH\DocTest\Location\FileLocation;
use TH\DocTest\TestCase;

/**
 * @implements \IteratorAggregate<string,\ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction|TestCase\SourceError>
 */
final class CommentReflectionSources implements \IteratorAggregate
{
    /**
     * @param array<string> $paths paths to files and folders to look for PHP comments sources in
     */
    public function __construct(
        private array $paths,
    ) {
    }

    /**
     * @return \Traversable<\ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction|TestCase\SourceError>
     */
    public function getIterator(): \Traversable
    {
        $files = [];

        foreach (new Files($this->paths) as $file) {
            $originalPath = $file->getPathname();

            $path = self::resolveIncludePath($originalPath);

            if ($path instanceof TestCase\SourceError) {
                yield $path;

                continue;
            }

            $error = self::load($path, $originalPath);
            include_once $files[] = $path;

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
            $output = \ob_get_contents();

            if ($output !== "") {
                echo "output", PHP_EOL;
            }

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
                new \RuntimeException("Could not resolve path to a file to include: $path"),
            );
        }

        return $resolvedPath;
    }

    /**
     * @param \Traversable<\ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction> $commentReflectionSources
     * @param array<string> $files
     * @return \Traversable<\ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction>
     */
    private function declaredInFiles(\Traversable $commentReflectionSources, array $files): \Traversable
    {
        foreach ($commentReflectionSources as $commentReflectionSource) {
            if (\in_array($commentReflectionSource->getFileName(), $files, true)) {
                yield $commentReflectionSource;
            }
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
        foreach (\get_defined_functions()["user"] as $functionName) {
            try {
                yield new \ReflectionFunction($functionName);
            } catch (ReflectionException) {
            }
        }
    }
}
