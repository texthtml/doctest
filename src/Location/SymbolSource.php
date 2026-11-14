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
    /**
     * @param list<string> $setups ExamplesSetup attribute instantiation expressions,
     *                             name-resolved and printable (`new \…(…)`)
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $name,
        /** short (unqualified) name of the enclosing declaration, used to build the runtime namespace */
        public readonly string $ownerShort,
        /** fqcn (or namespace for functions) to `use` in the eval header */
        public readonly string $useTarget,
        public readonly string $docComment,
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
