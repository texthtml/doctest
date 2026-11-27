<?php

declare(strict_types=1);

namespace TH\DocTest\Mago;

/**
 * reads mago's JSON output: issue reports (`--reporting-format=json`) and the
 * effective configuration (`mago config`)
 *
 * @internal
 *
 * @psalm-type Span = array{file_id: array{name: string}, start: array{line: int}}
 * @psalm-type Annotation = array{kind: string, message: string, span: Span}
 * @psalm-type ReportedIssue = array{level: string, code: string, message: string, annotations: list<Annotation>}
 */
final class Report
{
    /**
     * @param array<mixed> $report decoded `--reporting-format=json` output
     *
     * @return list<array{level: string, code: string, message: string, file: string, line: int}> each
     *     issue at the start of its primary annotation (`file` relative to the
     *     workspace, `line` 1-based), issues without location are left out
     */
    public static function issues(array $report): array
    {
        /** @var list<ReportedIssue> $reported the documented shape of mago's JSON report */
        $reported = $report['issues'] ?? [];
        $issues = [];

        foreach ($reported as $issue) {
            $span = self::primarySpan($issue['annotations']);

            if ($span === null) {
                continue;
            }

            $issues[] = [
                'level' => \strtolower($issue['level']),
                'code' => $issue['code'],
                'message' => self::message($issue),
                'file' => \str_replace(search: '\\', replace: '/', subject: $span['file_id']['name']),
                // mago reports 0-based lines
                'line' => $span['start']['line'] + 1,
            ];
        }

        return $issues;
    }

    /**
     * the `minimum-fail-level` of a mago command: like when running that
     * command on the project's code, issues below it don't fail
     *
     * @param array<mixed> $config the effective configuration (`mago config`)
     *
     * @return key-of<ExportedExample::LEVELS>
     */
    public static function minimumFailLevel(array $config, string $section): string
    {
        /** @var array{minimum-fail-level?: string} $settings */
        $settings = \is_array($config[$section] ?? null) ? $config[$section] : [];
        $level = \strtolower($settings['minimum-fail-level'] ?? 'error');

        return \array_key_exists($level, ExportedExample::LEVELS) ? $level : 'error';
    }

    /**
     * the generic parse error message is completed by its annotation, which
     * says what was unexpected
     *
     * @param ReportedIssue $issue
     */
    private static function message(array $issue): string
    {
        $detail = $issue['annotations'][0]['message'] ?? '';

        if ($issue['code'] !== 'parse' || $detail === '') {
            return $issue['message'];
        }

        return "{$issue['message']}: {$detail}";
    }

    /**
     * @param list<Annotation> $annotations
     *
     * @return Span|null
     */
    private static function primarySpan(array $annotations): ?array
    {
        foreach ($annotations as $annotation) {
            if ($annotation['kind'] === 'Primary') {
                return $annotation['span'];
            }
        }

        return $annotations[0]['span'] ?? null;
    }
}
