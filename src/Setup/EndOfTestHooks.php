<?php

declare(strict_types=1);

namespace TH\DocTest\Setup;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * routes the eval'd example code to the current run's {@see EndOfTest} hooks:
 * examples end with a single `EndOfTestHooks::invoke()` call — inside a
 * `finally`, so abrupt endings (`return`, uncaught exception) still trigger the
 * hooks — which runs them inside the example's scope, while its variables are
 * still alive. Each hook is isolated: one failing must not prevent the others
 * from running (nor mask a failure the example is about to propagate), it logs
 * a warning instead. Library internals — not user code — should call this
 *
 * @internal
 */
final class EndOfTestHooks
{
    /**
     * hook callables paired with a printable label (e.g. `Ns\Setup::endOfTest`)
     * used in the warning when the hook throws
     *
     * @var list<array{callable(): void, string}>
     */
    public static array $hooks = [];

    private static ?LoggerInterface $logger = null;

    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    public static function invoke(): void
    {
        foreach (self::$hooks as [$hook, $label]) {
            try {
                $hook();
            } catch (\Throwable $th) {
                (self::$logger ??= new NullLogger())->warning('{hook} threw {class}: {message}', [
                    'hook' => $label,
                    'class' => $th::class,
                    'message' => $th->getMessage(),
                    'exception' => $th,
                ]);
            }
        }
    }
}
