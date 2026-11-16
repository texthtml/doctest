<?php

declare(strict_types=1);

namespace TH\DocTest\Mago;

/**
 * invokes the mago executable from the workspace (so it picks up the project's
 * `mago.toml`)
 *
 * @internal
 */
final class Cli
{
    public function __construct(
        /** @var non-empty-list<string> how to invoke mago, e.g. `[PHP_BINARY, 'vendor/bin/mago']` */
        private readonly array $command,
        /** @var non-empty-string */
        public readonly string $workspace,
    ) {}

    /**
     * mago from the project's `vendor/bin` when installed there, from the
     * `PATH` otherwise
     *
     * @param non-empty-string $workspace
     */
    public static function fromWorkspace(string $workspace, ?string $binary = null): self
    {
        $binary ??= \is_file("{$workspace}/vendor/bin/mago") ? "{$workspace}/vendor/bin/mago" : 'mago';

        return new self(self::isPhpScript($binary) ? [\PHP_BINARY, $binary] : [$binary], $workspace);
    }

    /**
     * @param non-empty-list<string> $arguments
     *
     * @return array<mixed> decoded JSON output (mago reports issues with a
     *     non-zero exit code: only an unreadable output is a failure)
     */
    public function json(array $arguments): array
    {
        [$exitCode, $output, $errors] = $this->execute($arguments);

        try {
            /** @var mixed $decoded untyped until checked below */
            $decoded = \json_decode($output, associative: true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $decoded = null;
        }

        if (!\is_array($decoded)) {
            throw self::failure($arguments, $exitCode, $output . PHP_EOL . $errors);
        }

        return $decoded;
    }

    /**
     * @return list<string> the files the given command processes, relative to the workspace
     */
    public function files(string $command): array
    {
        $arguments = ['list-files', "--command={$command}", '--zero-terminate'];
        [$exitCode, $output, $errors] = $this->execute($arguments);

        if ($exitCode !== 0) {
            throw self::failure($arguments, $exitCode, $errors);
        }

        return \array_values(\array_filter(\explode("\0", $output), static fn(string $path): bool => $path !== ''));
    }

    /**
     * @param non-empty-list<string> $arguments
     *
     * @return array{int, string, string} exit code, stdout & stderr
     */
    private function execute(array $arguments): array
    {
        $command = [...$this->command, '--colors=never', ...$arguments];

        // files rather than pipes: reading one pipe while mago blocks on
        // writing to the other, full, one would deadlock
        $stdout = self::temporaryFile();
        $stderr = self::temporaryFile();

        $pipes = [];

        try {
            $process = \proc_open(
                $command,
                [
                    0 => ['file', \PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
                    1 => ['file', $stdout, 'w'],
                    2 => ['file', $stderr, 'w'],
                ],
                $pipes,
                $this->workspace,
            );

            if ($process === false) {
                throw new \RuntimeException('Failed to run: ' . \implode(separator: ' ', array: $command));
            }

            $exitCode = \proc_close($process);

            return [$exitCode, (string) \file_get_contents($stdout), (string) \file_get_contents($stderr)];
        } finally {
            \unlink($stdout);
            \unlink($stderr);
        }
    }

    /**
     * @return non-empty-string
     */
    private static function temporaryFile(): string
    {
        $path = \tempnam(\sys_get_temp_dir(), prefix: 'doctest-mago-');

        if ($path === false || $path === '') {
            throw new \RuntimeException('Failed to create a temporary file for mago output');
        }

        return $path;
    }

    /**
     * @param list<string> $arguments
     */
    private static function failure(array $arguments, int $exitCode, string $output): \RuntimeException
    {
        $command = \implode(separator: ' ', array: $arguments);
        $output = \trim($output);

        return new \RuntimeException("`mago {$command}` failed (exit code {$exitCode}):" . PHP_EOL . $output);
    }

    private static function isPhpScript(string $binary): bool
    {
        if (\str_ends_with($binary, '.php') || \str_ends_with($binary, '.phar')) {
            return true;
        }

        $handle = \is_file($binary) ? \fopen($binary, mode: 'r') : false;

        if ($handle === false) {
            return false;
        }

        $start = (string) \fread($handle, length: 64);
        \fclose($handle);

        return \str_starts_with($start, '<?php') || \preg_match('/^#!.*\bphp\b/', $start) === 1;
    }
}
