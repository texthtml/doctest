<?php

declare(strict_types=1);

namespace TH\DocTest\Setup;

/**
 * implemented by a {@see \TH\DocTest\Attributes\ExamplesSetup} setup class that
 * wants to be notified when its example ends, success or failure: called before
 * the setup object is released, as the very last step of the example's lifecycle
 */
interface AfterExample
{
    public function afterExample(): void;
}
