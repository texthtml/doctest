<?php

declare(strict_types=1);

namespace TH\DocTest\Mago;

/**
 * temporary directory the examples are exported to, inside the workspace so
 * Mago applies the project's configuration to it
 *
 * @internal
 */
final class ExportDirectory
{
    private function __construct(
        /** relative to the workspace */
        public readonly string $name,
        private readonly string $path,
    ) {}

    /**
     * @param list<string> $subdirectories
     */
    public static function create(string $workspace, array $subdirectories): self
    {
        $name = '.doctest-mago-' . \bin2hex(\random_bytes(6));
        $directory = new self($name, "{$workspace}/{$name}");

        foreach (['', ...$subdirectories] as $subdirectory) {
            $path = \rtrim("{$directory->path}/{$subdirectory}", characters: '/');

            if (!\mkdir($path)) {
                $directory->remove();

                throw new \RuntimeException("Failed to create {$path}");
            }
        }

        return $directory;
    }

    /**
     * @param string $file path relative to the directory
     */
    public function write(string $file, string $contents): void
    {
        if (\file_put_contents("{$this->path}/{$file}", $contents) === false) {
            throw new \RuntimeException("Failed to write {$this->path}/{$file}");
        }
    }

    /**
     * @param string $file path relative to the directory
     */
    public function copy(string $source, string $file): void
    {
        if (!\copy($source, "{$this->path}/{$file}")) {
            throw new \RuntimeException("Failed to copy {$source} to {$this->path}/{$file}");
        }
    }

    public function remove(): void
    {
        self::removeRecursively($this->path);
    }

    private static function removeRecursively(string $path): void
    {
        if (!\is_dir($path)) {
            if (\file_exists($path)) {
                \unlink($path);
            }

            return;
        }

        foreach (\array_diff((array) \scandir($path), ['.', '..']) as $entry) {
            self::removeRecursively("{$path}/{$entry}");
        }

        \rmdir($path);
    }
}
