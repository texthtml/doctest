<?php

/**
 * ```php
 * $ratio = new Ratio(0.3);
 * echo $ratio->formatPercentage(); // @prints 30%
 * ```
 *
 * ```php
 * $ratio = new Ratio(0.9);
 * $ratio->value = 0.1; // @throws Error Cannot modify readonly property Ratio::$value
 * ```
 */
final class Ratio
{
    /**
     * ```php
     * new Ratio(log(0)); // @throws InvalidArgumentException Ratio only accepts finite values. Got -INF
     * ```
     */
    public function __construct(
        public readonly float $value,
    ) {
        if (!is_finite($value)) {
            throw new InvalidArgumentException("Ratio only accepts finite values. Got $value");
        }
    }

    /**
     * ```php
     * assert(Ratio::fromPercentage(80)->value === 0.8);
     * ```
     */
    public static function fromPercentage(float $percentage): Ratio
    {
        return new self($percentage / 100);
    }

    /**
     * ```php
     * $ratio = new Ratio(0.167);
     * self::assertEq($ratio->formatPercentage(2), "16.70%");
     * ```
     */
    public function formatPercentage(int $decimals = 0): string
    {
        return number_format($this->value * 100, $decimals) . "%";
    }
}
