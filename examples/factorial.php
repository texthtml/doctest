<?php

/**
 * Compute the factorial of a non-negative $n
 *
 * ```php
 * assert(factorial(0) === 1);
 * assert(factorial(1) === 1);
 * assert(factorial(2) === 2);
 * assert(factorial(3) === 6);
 * ```
 *
 * ```php
 * // You can use `self::assert*` to call `Webmozart\Assert\Assert::*` assertion helpers
 * self::assertEq(factorial(4), 24);
 * self::assertEq(factorial(5), 120);
 * ```
 *
 * ```php
 * // This example will fail because of unexpected output
 * echo factorial(6);
 * ```
 *
 * ```php
 * // You can check for expected output with one or more `@prints [text]` inline comments
 * echo factorial(6); // @prints 720
 * ```
 *
 * ```php
 * // This example will fail with InvalidArgumentException unexpected negative integer: -10
 * factorial(-10);
 * ```
 *
 * ```php
 * // You can check for Exception with a `@throws [Exception class] [Exception message]` inline comment
 * factorial(-10); // @throws InvalidArgumentException unexpected negative integer: -10
 *
 * // Everything after the exception will be ignored. So this won't fail:
 * assert(1 === 2);
 * ```
 *
 * ```php
 * // This example will fail with the message : "Expected a value equal to 3628890. Got: 3628800"
 * self::assertEq(factorial(10), 3628890);
 * ```
 */
function factorial(int $n): int {
    if ($n === 0) {
        return 1;
    }

    if ($n < 0) {
        throw new \InvalidArgumentException("unexpected negative integer: $n");
    }

    return $n * factorial($n - 1);
}
