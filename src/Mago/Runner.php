<?php

declare(strict_types=1);

namespace TH\DocTest\Mago;

use TH\DocTest\TestCase\Example;

/**
 * runs `mago lint` & `mago analyze` on examples: each one is exported (see
 * {@see ExportedExample}) to a temporary directory inside the workspace (so
 * mago applies the project's `mago.toml` and sees its sources & `vendor`),
 * and the reported issues are mapped back to the documented files
 *
 * @internal
 */
final class Runner
{
    public function __construct(
        private readonly Cli $mago,
        /** @var non-empty-list<Command> */
        private readonly array $commands = [Command::Lint, Command::Analyze],
    ) {}

    /**
     * @param list<Example> $examples
     *
     * @return \ArrayAccess<Example, list<Issue>>&\Countable the issues failing
     *     each example (examples without issues are left out)
     */
    public function check(array $examples): \ArrayAccess&\Countable
    {
        /** @var \WeakMap<Example, list<Issue>> $issues */
        $issues = new \WeakMap();

        if ($examples === []) {
            return $issues;
        }

        $directory = ExportDirectory::create($this->mago->workspace, ['examples', 'context']);

        try {
            $exported = $this->export($examples, $directory);
            $config = $this->mago->json(['config']);

            foreach ($this->commands as $command) {
                $this->collect(
                    $this->mago->json([$command->value, '--reporting-format=json', $command->path($directory)]),
                    Report::minimumFailLevel($config, $command->section()),
                    $exported,
                    $issues,
                );
            }
        } finally {
            $directory->remove();
        }

        return $issues;
    }

    /**
     * writes the examples to `examples/`, and, when analyzing, what they need
     * to be analyzed to `context/`
     *
     * @param list<Example> $examples
     *
     * @return array<string, ExportedExample> keyed by exported file path, relative to the workspace
     */
    private function export(array $examples, ExportDirectory $directory): array
    {
        $exported = [];

        foreach ($examples as $index => $example) {
            $exportedExample = new ExportedExample($example);
            $file = "examples/example-{$index}.php";

            $directory->write($file, $exportedExample->source());
            $exported["{$directory->name}/{$file}"] = $exportedExample;
        }

        if (\in_array(Command::Analyze, $this->commands, strict: true)) {
            $this->exportContext($examples, $directory);
        }

        return $exported;
    }

    /**
     * the {@see AssertionsStub}, and a copy of the documented files mago would
     * not see otherwise (those outside of, or excluded from, the project's mago
     * sources — e.g. `examples/`)
     *
     * @param list<Example> $examples
     */
    private function exportContext(array $examples, ExportDirectory $directory): void
    {
        $directory->write('context/assertions-stub.php', AssertionsStub::source());

        $analyzed = \array_fill_keys($this->mago->files(command: 'analyzer'), value: true);

        foreach ($examples as $index => $example) {
            $documented = $example->location()->path;
            // locations are relative to the workspace, unless outside of it
            $source = \str_starts_with($documented, '/') ? $documented : "{$this->mago->workspace}/{$documented}";

            if (!\array_key_exists($documented, $analyzed) && \is_file($source)) {
                $analyzed[$documented] = true;
                $directory->copy($source, "context/source-{$index}.php");
            }
        }
    }

    /**
     * @param array<mixed> $report decoded mago JSON report
     * @param key-of<ExportedExample::LEVELS> $minimumFailLevel
     * @param array<string, ExportedExample> $exported keyed by exported file path
     * @param \WeakMap<Example, list<Issue>> $issues
     */
    private function collect(array $report, string $minimumFailLevel, array $exported, \WeakMap $issues): void
    {
        foreach (Report::issues($report) as $reported) {
            $exportedExample = $exported[$reported['file']] ?? null;

            if ($exportedExample === null) {
                continue;
            }

            $issue = $exportedExample->issue(
                $reported['level'],
                $reported['code'],
                $reported['message'],
                $reported['line'],
                $minimumFailLevel,
            );

            $example = $exportedExample->example;
            $known = $issues[$example] ?? [];

            // a syntax error is reported many times (and by both commands)
            if ($issue !== null && !\in_array((string) $issue, \array_map(\strval(...), $known), strict: true)) {
                $issues[$example] = [...$known, $issue];
            }
        }
    }
}
