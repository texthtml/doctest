<?php

declare(strict_types=1);

namespace TH\DocTest\Location;

/**
 * Static stand-in for the reflection source a doc comment was found on: the
 * scanner never includes the analyzed files, so symbol resolution (eval header,
 * display name, setups) is driven by data harvested from parsing instead.
 *
 * @mago-expect lint:excessive-parameter-list
 */
final class SymbolSource
{
    public function __construct(
        /** class, method or function */
        public readonly string $kind,
        /** fully qualified display name (e.g. `Ns\Class::method(…)`, `Ns\function_name`) */
        public readonly string $name,
        /** short (unqualified) name of the enclosing declaration, used to build the runtime namespace */
        public readonly string $ownerShort,
        /** fqcn (or namespace for functions) to `use` in the eval header */
        public readonly string $useTarget,
        public readonly string $docComment,
        /**
         * ExamplesSetup attribute instantiation expressions, name-resolved and
         * printable (`new \…(…)`)
         *
         * @var list<string>
         */
        public readonly array $setups,
        public readonly string $path,
        /** line of the doc comment's last line (the `*\/` one) */
        public readonly ?int $docEndLine,
    ) {}

    public function isMethod(): bool
    {
        return $this->kind === 'method';
    }
}
