<?php

declare(strict_types=1);

namespace TH\DocTest\Mago;

/**
 * PHP source of a reflected function's parameter list, with fully qualified
 * class names so it can be printed in any namespace
 *
 * @internal
 */
final class Signature
{
    public static function parameters(\ReflectionFunctionAbstract $function): string
    {
        return \implode(separator: ', ', array: \array_map(self::parameter(...), $function->getParameters()));
    }

    private static function parameter(\ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();

        return (
            ($type === null ? '' : self::type($type) . ' ')
            . ($parameter->isPassedByReference() ? '&' : '')
            . ($parameter->isVariadic() ? '...' : '')
            . "\${$parameter->getName()}"
            . ($parameter->isDefaultValueAvailable() ? ' = ' . self::defaultValue($parameter) : '')
        );
    }

    private static function defaultValue(\ReflectionParameter $parameter): string
    {
        $constant = $parameter->isDefaultValueConstant() ? $parameter->getDefaultValueConstantName() : null;

        if ($constant !== null) {
            return '\\' . \ltrim($constant, characters: '\\');
        }

        return \var_export($parameter->getDefaultValue(), return: true);
    }

    private static function type(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionUnionType) {
            return \implode(separator: '|', array: \array_map(static fn(\ReflectionType $type): string => $type
                instanceof \ReflectionIntersectionType
                    ? '(' . self::type($type) . ')'
                    : self::type($type), $type->getTypes()));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return \implode(separator: '&', array: \array_map(self::type(...), $type->getTypes()));
        }

        \assert($type instanceof \ReflectionNamedType, description: 'only named, union & intersection types exist');

        $name = $type->getName();
        $nullable = $type->allowsNull() && $name !== 'mixed' && $name !== 'null';

        return ($nullable ? '?' : '') . ($type->isBuiltin() ? $name : "\\{$name}");
    }
}
