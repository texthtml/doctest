<?php

declare(strict_types=1);

namespace Examples;

use TH\DocTest\Attributes\ExamplesSetup;
use TH\DocTest\SetUp;

/**
 * A setup keeps its state reachable from examples through a static accessor:
 * the attribute's `new` expression runs before every example below, so
 * `Recorder::$current` always returns this example's own fresh instance.
 */
final class Recorder implements SetUp
{
    public static self $current;

    /** @var list<string> */
    public array $printed = [];

    public function __construct()
    {
        self::$current = $this;
    }

    public function record(string $line): void
    {
        $this->printed[] = $line;
    }
}

#[ExamplesSetup(new Recorder())]
/**
 * ```php
 * // a fresh Recorder was built before this example started
 * assert(\Examples\Recorder::$current->printed === []);
 * echo "hello"; // @prints hello
 * ```
 */
function hello(): void {}

#[ExamplesSetup(new Recorder())]
/**
 * ```php
 * // each example gets its own recorder: nothing leaked from `hello()`
 * assert(\Examples\Recorder::$current->printed === []);
 *
 * // setups can expose helpers to route output into the recorder
 * \Examples\Recorder::$current->record("world");
 * assert(\Examples\Recorder::$current->printed === ['world']);
 * ```
 */
function world(): void {}
