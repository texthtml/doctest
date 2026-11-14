<?php

declare(strict_types=1);

namespace TH\DocTest\Setup;

/**
 * implemented by a {@see \TH\DocTest\Attributes\ExamplesSetup} setup class that
 * wants to run code at the end of each example *while the example's variables
 * are still alive*: the hook is appended to the example code itself, so it runs
 * before the example's scope teardown — unlike an `AfterExample` hook, which
 * runs after every local has already been destroyed. The hook does not run when
 * the example itself ends abruptly (uncaught exception, `return` statement)
 */
interface EndOfTest
{
    public function endOfTest(): void;
}
