<?php

declare(strict_types=1);

namespace TH\DocTest\Setup;

/**
 * implemented by a {@see \TH\DocTest\Attributes\ExamplesSetup} setup class that
 * wants to run code at the end of each example *while the example's variables
 * are still alive*: the hook is appended to the example code itself (in a
 * `finally`, so it also runs on abrupt endings — `return`, uncaught exception —
 * which still propagate), before the example's scope teardown — unlike an
 * `AfterExample` hook, which runs after every local has already been destroyed
 */
interface EndOfTest
{
    public function endOfTest(): void;
}
