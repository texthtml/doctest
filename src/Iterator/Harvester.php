<?php

declare(strict_types=1);

namespace TH\DocTest\Iterator;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects namespace/import context and every doc-comment-bearing symbol.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class Harvester extends NodeVisitorAbstract
{
    private string $namespace = '';

    private ?Stmt\ClassLike $currentClass = null;

    private string $path = '';

    /**
     * raw token stream of the current file: doc comments sitting between an
     * attribute group and the declaration are dropped by php-parser, so they
     * are recovered from the tokens
     *
     * @var array<int,\PhpParser\Token>|null
     */
    private ?array $tokens = null;

    /**
     * @var list<\TH\DocTest\Location\SymbolSource>
     */
    public array $symbols = [];

    public function __construct(
        private readonly SymbolFactory $factory,
    ) {}

    public function setCurrentPath(string $path): void
    {
        $this->path = $path;
        // each file is an independent compilation unit: file-scoped state must
        // not leak from one parsed file into the next
        $this->namespace = '';
        $this->currentClass = null;
    }

    /**
     * @param array<\PhpParser\Token> $tokens
     */
    public function setSourceTokens(array $tokens): void
    {
        // token streams from the parser are always a contiguous 0-based list
        $this->tokens = \array_values($tokens);
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->namespace = $node->name === null ? '' : $node->name->toString();
            $this->currentClass = null;

            return null;
        }

        if ($node instanceof Stmt\ClassLike) {
            $this->currentClass = $node;
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Stmt\ClassLike && $this->currentClass === $node) {
            $this->currentClass = null;
        }

        // symbols are collected on leave: children (e.g. setup attribute
        // arguments) are fully name-resolved by then, while on enter they are
        // not yet — NameResolver resolves expression names as it descends
        $docComment = $node->getDocComment();

        if ($docComment !== null) {
            $recovered = [$docComment->getText(), $docComment->getEndLine()];
        } else {
            $recovered = $this->recoverDocComment($node);
        }

        if ($recovered === null) {
            return null;
        }

        $this->factory->setContext($this->namespace, $this->path, $this->currentClass);
        $symbol = $this->factory->symbol($node, $recovered);

        if ($symbol !== null) {
            $this->symbols[] = $symbol;
        }

        return null;
    }

    /**
     * php-parser attributes a `/** … *\/` comment sitting between an attribute
     * group (`#[…]`) and the declaration to nobody: `getDocComment()` returns
     * null even though PHP's own reflection reports it. Scanning the raw token
     * stream for it recovers both the comment and its end line.
     *
     * @return array{string, int}|null doc comment text and its 1-based end line
     */
    private function recoverDocComment(Node $node): ?array
    {
        $attributes = $node->getAttributes();

        if (
            $this->tokens === null
            || !$node instanceof Stmt\Function_
            && !$node instanceof Stmt\ClassMethod
            && !$node instanceof Stmt\ClassLike
            || $node->attrGroups === []
            || !\array_key_exists('startTokenPos', $attributes)
            || !\is_int($attributes['startTokenPos'])
        ) {
            return null;
        }

        $tokens = $this->tokens;
        $tokenPos = $attributes['startTokenPos'];

        // the declaration's span begins at its first attribute group (`#[`, a
        // single T_ATTRIBUTE token): skip to the group's matching `]`, then
        // continue over whitespace and stray comments to recover the doc
        // comment sitting between the attribute group and the declaration
        $depth = 0;

        while ($tokenPos < \count($tokens)) {
            $token = $tokens[$tokenPos];

            if ($token->is(\T_ATTRIBUTE)) {
                $depth++;
                $tokenPos++;

                continue;
            }

            if ($depth > 0) {
                if ($token->text === '[') {
                    $depth++;
                } elseif ($token->text === ']') {
                    $depth--;
                }

                $tokenPos++;

                continue;
            }

            if ($token->is(\T_DOC_COMMENT)) {
                return [$token->text, $token->getEndLine()];
            }

            if ($token->is(\T_COMMENT) || $token->is(\T_WHITESPACE)) {
                $tokenPos++;

                continue;
            }

            break;
        }

        return null;
    }
}
