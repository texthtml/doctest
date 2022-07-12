<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use ReflectionException;

/**
 * @implements \IteratorAggregate<string,\ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction>
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
     * @return \Traversable<\ReflectionClass<object>|\ReflectionMethod|\ReflectionFunction>
     */
    public function getIterator(): \Traversable
    {
        $files = [];

        foreach (new Files($this->paths) as $file) {
            $path = \stream_resolve_include_path($file->getPathname());

            if ($path === false) {
                throw new \RuntimeException("Could not resolve path to a file to include: $path");
            }

            include_once $files[] = $path;
        }

        yield from $this->declaredInFiles($this->interfaces(), $files);
        yield from $this->declaredInFiles($this->classes(), $files);
        yield from $this->declaredInFiles($this->functions(), $files);
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
