<?php

declare(strict_types=1);

namespace TH\DocTest\Iterator;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use Psr\Log\LoggerInterface;
use TH\DocTest\Location\SymbolSource;

/**
 * Harvests doc-comment-bearing symbols from PHP files without executing them:
 * a parse error is reported per file (yielding nothing for it) instead of
 * aborting discovery, and no code under test runs at scan time.
 *
 * @implements \IteratorAggregate<SymbolSource>
 */
final class StaticCommentSources implements \IteratorAggregate
{
    private readonly Parser $parser;

    private readonly PrettyPrinter $printer;

    public function __construct(
        /**
         * paths to files and folders to look for PHP comments sources in
         *
         * @var array<string>
         */
        private readonly array $paths,
        private readonly LoggerInterface $logger,
    ) {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->printer = new PrettyPrinter();
    }

    /**
     * @return \Traversable<SymbolSource>
     */
    public function getIterator(): \Traversable
    {
        $harvester = new Harvester(new SymbolFactory($this->printer));
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($harvester);

        foreach (new Files($this->paths, $this->logger) as $file) {
            $path = $file->getPathname();
            $statements = $this->parse($path);

            if ($statements === null) {
                continue;
            }

            $harvester->setCurrentPath($path);
            $harvester->setSourceTokens($this->parser->getTokens());
            $traverser->traverse($statements);
        }

        // reflection yields a declaring class's doc comment before its methods':
        // keep the static backend's ordering identical (stable per-file sort)
        $symbols = $harvester->symbols;
        usort(
            $symbols,
            static fn(SymbolSource $a, SymbolSource $b): int => (
                ($a->kind === 'class' ? 0 : 1) <=> ($b->kind === 'class' ? 0 : 1)
            ),
        );

        yield from $symbols;
    }

    /**
     * @return array<Node\Stmt>|null null when the file cannot be parsed
     */
    private function parse(string $path): ?array
    {
        $contents = \file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        try {
            return $this->parser->parse($contents);
        } catch (\PhpParser\Error) {
            return null;
        }
    }
}
