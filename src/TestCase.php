<?php declare(strict_types=1);

namespace TH\DocTest;

use TH\DocTest\Location\Location;

interface TestCase
{
    public function location(): Location;

    public function eval(): void;

    public function expectedFailure(): ?TestCase\ExpectedFailure;

    public function expectedOutput(): string;
}
