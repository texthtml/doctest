<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Data\Mago;

use TH\DocTest\Attributes\ExamplesSetup;

/**
 * ```php
 * self::assertSame(Mago\clean(2), 4);
 * echo Mago\clean(3); // @prints 6
 * Mago\clean(-1); // @throws InvalidArgumentException negative
 * ```
 */
function clean(int $n): int
{
    if ($n < 0) {
        throw new \InvalidArgumentException('negative');
    }

    return $n * 2;
}

/**
 * ```php
 * $doubled = Mago\clean(2);
 *
 * Mago\clean('two');
 * ```
 */
function wrong_argument(): void {}

/**
 * ```php
 * self::assertNope(Mago\clean(2));
 * ```
 */
function unknown_assertion(): void {}

#[ExamplesSetup(new \stdClass())]
/**
 * ```php
 * self::assertCustom(Mago\clean(2));
 * ```
 */
function custom_assertion(): void {}

/**
 * ```php
 * use Webmozart\Assert\Assert;
 *
 * $value = \random_int(0, 1) === 1 ? 'one' : 1;
 * self::assertString($value);
 * Assert::true(\strlen($value) > 0);
 * ```
 */
function narrowing(): void {}

/**
 * ```php
 * $value = \random_int(0, 1) === 1 ? 'one' : 1;
 * echo \strlen($value);
 * ```
 */
function not_narrowed(): void {}

/**
 * ```php
 * if (Mago\clean(2) === 4) {
 *     echo 'unclosed';
 * ```
 */
function parse_error(): void {}
