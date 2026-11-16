<?php

declare(strict_types=1);

namespace TH\DocTest;

use TH\DocTest\Location\Location;
use TH\Maybe\Option;

interface TestCase
{
    public function location(): Location;

    public function eval(): void;

    /**
     * @return Option<TestCase\ExpectedFailure>
     */
    public function expectedFailure(): Option;

    public function expectedOutput(): string;
}
