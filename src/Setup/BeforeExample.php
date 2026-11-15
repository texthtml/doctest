<?php

declare(strict_types=1);

namespace TH\DocTest\Setup;

/**
 * implemented by a {@see \TH\DocTest\Attributes\ExamplesSetup} setup class that
 * wants to prepare itself right before its example runs (fresh instance is
 * already constructed, but the example hasn't started yet)
 */
interface BeforeExample
{
    public function beforeExample(): void;
}
