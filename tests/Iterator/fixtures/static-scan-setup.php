<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Iterator;

use TH\DocTest\Attributes\ExamplesSetup;

#[ExamplesSetup(new ScannedSetup())]
/**
 * ```php
 * assert(scanned_function() === 42);
 * ```
 */
function scanned_function(): int
{
    return 42;
}
