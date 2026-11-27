<?php

declare(strict_types=1);

namespace TH\DocTest\Mago;

use TH\DocTest\Attributes\ExamplesSetup;
use TH\DocTest\Location\SymbolSource;
use TH\DocTest\TestCase\Example;
use Webmozart\Assert\Assert;

/**
 * generates the PHP source of the classes the exported examples extend, so Mago
 * can resolve the `self::assert*()` calls that only exist at runtime through
 * {@see Example::__callStatic()}:
 *
 * - {@see self::STRICT}: one `assert{Name}()` static method per public
 *   Webmozart Assert assertion, with the original parameters and type-narrowing
 *   docblock tags (`@psalm-assert`, templates…), returning `void` like the
 *   runtime proxy does
 * - {@see self::PERMISSIVE}: the same, plus a `__callStatic()` fallback for
 *   examples whose setups may register {@see \TH\DocTest\Setup\CustomAssertions}
 *
 * the classes live in Webmozart Assert's namespace (with its imports), so the
 * relative class names of the copied docblocks keep resolving
 *
 * @internal
 */
final class AssertionsStub
{
    public const NAMESPACE = 'Webmozart\\Assert';

    public const STRICT = self::NAMESPACE . '\\DoctestExampleAssertions';

    public const PERMISSIVE = self::NAMESPACE . '\\DoctestExamplePermissiveAssertions';

    private const KEPT_DOC_TAGS = '/@(?:(?:psalm|phpstan)-)?(?:assert|template|param)\b/';

    public static function source(): string
    {
        $assert = new \ReflectionClass(Assert::class);
        $methods = [];
        $files = [];

        foreach ($assert->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$method->isStatic() || \str_starts_with($method->getName(), '__')) {
                continue;
            }

            $methods[] = self::method($method);
            $files[(string) $method->getFileName()] = true;
        }

        $strict = self::shortName(self::STRICT);
        $permissive = self::shortName(self::PERMISSIVE);

        return \implode(PHP_EOL, [
            '<?php',
            '',
            'namespace ' . self::NAMESPACE . ';',
            '',
            ...self::imports(\array_keys($files)),
            '',
            "abstract class {$strict}",
            '{',
            ...$methods,
            '}',
            '',
            "abstract class {$permissive} extends {$strict}",
            '{',
            '    public static function __callStatic(string $name, array $arguments): void {}',
            '}',
            '',
        ]);
    }

    /**
     * {@see \TH\DocTest\Setup\CustomAssertions} come from setups, which can't
     * be inspected without instantiating them (or loading their class): any
     * example with setups gets the permissive stub
     *
     * @return self::STRICT|self::PERMISSIVE
     */
    public static function for(Example $example): string
    {
        $source = $example->location()->source;

        if ($source instanceof SymbolSource) {
            return $source->setups === [] ? self::STRICT : self::PERMISSIVE;
        }

        $attributes = $source->getAttributes(ExamplesSetup::class, \ReflectionAttribute::IS_INSTANCEOF);

        if ($source instanceof \ReflectionMethod) {
            $attributes = [
                ...$attributes,
                ...$source->getDeclaringClass()->getAttributes(
                    ExamplesSetup::class,
                    \ReflectionAttribute::IS_INSTANCEOF,
                ),
            ];
        }

        return $attributes === [] ? self::STRICT : self::PERMISSIVE;
    }

    private static function shortName(string $class): string
    {
        return \substr(string: $class, offset: \strlen(self::NAMESPACE) + 1);
    }

    private static function method(\ReflectionMethod $method): string
    {
        $name = 'assert' . \ucfirst($method->getName());
        $parameters = Signature::parameters($method);

        return self::docComment($method) . "    public static function {$name}({$parameters}): void {}" . PHP_EOL;
    }

    private static function docComment(\ReflectionMethod $method): string
    {
        $kept = \array_filter(
            \explode("\n", (string) $method->getDocComment()),
            static fn(string $line): bool => \preg_match(self::KEPT_DOC_TAGS, $line) === 1,
        );

        if ($kept === []) {
            return '';
        }

        $lines = \array_map(
            static fn(string $line): string => '     * ' . \ltrim(\ltrim($line), characters: '* '),
            $kept,
        );

        return '    /**' . PHP_EOL . \implode(PHP_EOL, $lines) . PHP_EOL . '     */' . PHP_EOL;
    }

    /**
     * `use` statements of the files declaring the assertions (Assert & its
     * traits), so the class names in the copied docblocks resolve like there
     *
     * @param list<string> $files
     *
     * @return list<string>
     */
    private static function imports(array $files): array
    {
        $imports = [];

        foreach ($files as $file) {
            $source = \is_file($file) ? (string) \file_get_contents($file) : '';
            $matches = [];

            \preg_match_all('/^use\s+[\w\\\\]+(?:\s+as\s+\w+)?\s*;/m', $source, $matches);

            foreach ($matches[0] as $import) {
                $imports[$import] = true;
            }
        }

        return \array_keys($imports);
    }
}
