<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Subscriber;

use Psr\Log\AbstractLogger;

/** minimal PSR-3 recorder: records messages by level for assertions */
final class RecordingLogger extends AbstractLogger
{
    /** @var array<string, list<string>> */
    public array $messages = [];

    public function log(mixed $level, mixed $message, array $context = []): void
    {
        $replacements = array_combine(
            array_map(static fn($key): string => '{' . $key . '}', array_keys($context)),
            array_map(strval(...), array_values($context)),
        );

        $this->messages[(string) $level] ??= [];
        $this->messages[(string) $level][] = strtr((string) $message, $replacements);
    }
}
