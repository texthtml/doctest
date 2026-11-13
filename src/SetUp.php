<?php

declare(strict_types=1);

namespace TH\DocTest;

/**
 * Marker interface for objects usable with the {@see Attributes\ExamplesSetup} attribute.
 *
 * You instantiate your setup yourself in the attribute argument — its constructor runs
 * once per tested example, with whatever arguments you choose:
 *
 * ```
 * #[Attributes\ExamplesSetup(new ClockFreezer('2024-01-01'))]
 * function myFunction(): void { … }
 * ```
 */
interface SetUp {}
