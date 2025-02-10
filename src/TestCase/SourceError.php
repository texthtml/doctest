<?php declare(strict_types=1);

namespace TH\DocTest\TestCase;

use TH\DocTest\Location\FileLocation;
use TH\DocTest\TestCase;

final class SourceError implements TestCase
{
    public function __construct(
        private readonly FileLocation $location,
        private readonly \Throwable $error,
    ) {}

    public function eval(): never
    {
        throw $this->error;
    }

    public function location(): FileLocation
    {
        return $this->location;
    }

    public function expectedFailure(): ?ExpectedFailure
    {
        return null;
    }

    public function expectedOutput(): string
    {
        return "";
    }
}
