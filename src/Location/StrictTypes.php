<?php

declare(strict_types=1);

namespace TH\DocTest\Location;

/**
 * whether a file declares `strict_types=1`: eval'd code doesn't inherit it from
 * any file, so examples get the documented file's mode by declaring it
 * themselves. The file is tokenized, never included (static scanning promise)
 *
 * @internal
 */
final class StrictTypes
{
    /** @var array<string, bool> */
    private static array $files = [];

    public static function declaredIn(string $file): bool
    {
        return self::$files[$file] ??= self::parse($file);
    }

    private static function parse(string $file): bool
    {
        $code = \is_file($file) ? \file_get_contents($file) : false;

        if ($code === false) {
            return false;
        }

        $directives = '';

        // `declare` statements can only come first: stop at the first other token
        foreach (\PhpToken::tokenize($code) as $index => $token) {
            $shebang = $index === 0 && $token->is(\T_INLINE_HTML) && \str_starts_with($token->text, '#!');

            if (
                $shebang
                || $token->isIgnorable()
                || $token->is([\T_DECLARE, '(', ')', ',', '=', \T_STRING, \T_LNUMBER])
            ) {
                $directives .= $shebang || $token->isIgnorable() ? '' : $token->text;

                continue;
            }

            if (!$token->is(';')) {
                break;
            }

            $directives .= ';';
        }

        return \preg_match('/declare\([^)]*\bstrict_types=1\b/i', $directives) === 1;
    }
}
