<?php

declare(strict_types=1);

namespace TH\DocTest\Iterator;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use TH\DocTest\Attributes\ExamplesSetup;
use TH\DocTest\Location\SymbolSource;

/**
 * Builds SymbolSources from parsed declaration nodes.
 *
 * the file-scoped context (namespace, path, enclosing class) is set once per
 * file rather than threaded through every call, keeping parameter lists short
 */
final class SymbolFactory
{
    private string $namespace = '';

    private string $path = '';

    private ?Stmt\ClassLike $currentClass = null;

    public function __construct(
        private readonly PrettyPrinter $printer,
    ) {}

    public function setContext(string $namespace, string $path, ?Stmt\ClassLike $currentClass): void
    {
        $this->namespace = $namespace;
        $this->path = $path;
        $this->currentClass = $currentClass;
    }

    /**
     * @param array{string, int} $docComment doc comment text and its 1-based end line
     *
     * @return SymbolSource|null null when the node carries no doc comment worth scanning
     */
    public function symbol(Node $node, array $docComment): ?SymbolSource
    {
        [$text, $endLine] = $docComment;

        if ($node instanceof Stmt\ClassLike && $node->name !== null) {
            return $this->classLike($node, $text, $endLine);
        }

        if ($node instanceof Stmt\ClassMethod && $this->currentClass?->name !== null) {
            return $this->method($node, $text, $endLine);
        }

        if ($node instanceof Stmt\Function_) {
            return $this->function($node, $text, $endLine);
        }

        return null;
    }

    private function classLike(Stmt\ClassLike $node, string $docComment, int $docEndLine): SymbolSource
    {
        $shortName = (string) $node->name;
        $fqcn = $this->namespace === '' ? $shortName : "{$this->namespace}\\{$shortName}";

        return new SymbolSource(
            kind: 'class',
            name: $fqcn,
            ownerShort: $shortName,
            useTarget: $fqcn,
            docComment: $docComment,
            setups: $this->setups($node),
            path: $this->path,
            docEndLine: $docEndLine,
        );
    }

    private function method(Stmt\ClassMethod $node, string $docComment, int $docEndLine): SymbolSource
    {
        $class = $this->currentClass;
        \assert($class !== null && $class->name !== null, description: 'caller guarantees a named class context');

        $shortClass = (string) $class->name;
        $fqcn = $this->namespace === '' ? $shortClass : "{$this->namespace}\\{$shortClass}";

        return new SymbolSource(
            kind: 'method',
            name: "{$fqcn}::{$node->name->name}(…)",
            ownerShort: $shortClass,
            useTarget: $fqcn,
            docComment: $docComment,
            setups: $this->setups($node),
            path: $this->path,
            docEndLine: $docEndLine,
        );
    }

    private function function(Stmt\Function_ $node, string $docComment, int $docEndLine): SymbolSource
    {
        $shortName = $node->name->toString();
        $fqcn = $this->namespace === '' ? $shortName : "{$this->namespace}\\{$shortName}";

        return new SymbolSource(
            kind: 'function',
            name: $fqcn,
            ownerShort: $shortName,
            useTarget: $this->namespace,
            docComment: $docComment,
            setups: $this->setups($node),
            path: $this->path,
            docEndLine: $docEndLine,
        );
    }

    /**
     * Instantiation expressions for the ExamplesSetup attributes on the node,
     * name-resolved so their arguments print with fully qualified class names.
     *
     * @return list<string>
     */
    private function setups(Stmt\ClassLike|Stmt\ClassMethod|Stmt\Function_ $node): array
    {
        $setups = [];

        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (!\in_array($attribute->name->toString(), [ExamplesSetup::class, 'ExamplesSetup'], strict: true)) {
                    continue;
                }

                $args = \implode(', ', \array_map(
                    fn(Node\Arg $arg): string => (
                        ($arg->name !== null ? "{$arg->name->name}: " : '')
                        . $this->printer->prettyPrintExpr($arg->value)
                    ),
                    $attribute->args,
                ));

                $setups[] = "new \\" . ExamplesSetup::class . "({$args})";
            }
        }

        return $setups;
    }
}
