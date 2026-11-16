<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Mago;

use PHPUnit\Framework\TestCase;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\Location\SymbolSource;
use TH\DocTest\Mago\AssertionsStub;
use TH\DocTest\Mago\ExportedExample;
use TH\DocTest\TestCase\Example;

final class ExportedExampleTest extends TestCase
{
    public function testTheExampleLinesKeepTheirPositionAfterTheOneLinePrelude(): void
    {
        $exported = new ExportedExample(self::example("use Foo\\Bar;\n\$bar = new Bar();\necho \$bar;"));

        static::assertSame(
            '<?php namespace TH\Doctest\Runtime\doc\Example1;use Ns;use Foo\Bar; final class DoctestExample extends \\'
            . AssertionsStub::STRICT
            . " { public function __invoke() {\n\n\$bar = new Bar();\necho \$bar;\n}}\n",
            $exported->source(),
        );
    }

    public function testExamplesWithSetupsMayUseCustomAssertions(): void
    {
        $exported = new ExportedExample(self::example('self::assertCustom(1);', setups: ['new \Setup()']));

        static::assertStringContainsString('extends \\' . AssertionsStub::PERMISSIVE . ' {', $exported->source());
        static::assertNull($exported->issue(
            'warning',
            'non-documented-method',
            'Ambiguous method call to `assertCustom` on class `TH\Doctest\Runtime\doc\Example1\DoctestExample`.',
            2,
            'warning',
        ));
    }

    public function testIssuesAreMappedToTheDocumentedFile(): void
    {
        $issue = (new ExportedExample(self::example("\$a = 1;\n\$b = 2;")))->issue(
            'error',
            'some-code',
            'about `TH\Doctest\Runtime\doc\Example1\DoctestExample`',
            3,
            'error',
        );

        static::assertSame('src/doc.php:11: error[some-code] about `self`', (string) $issue);
    }

    public function testIssuesBelowTheMinimumFailLevelAreIgnored(): void
    {
        $exported = new ExportedExample(self::example('$a = 1;'));

        static::assertNull($exported->issue('warning', 'some-code', 'message', 2, 'error'));
        static::assertNotNull($exported->issue('warning', 'some-code', 'message', 2, 'warning'));
    }

    public function testIssuesOnTheScaffoldingAreIgnoredExceptParseErrors(): void
    {
        $exported = new ExportedExample(self::example("if (true) {\n    echo 1;"));

        static::assertNull($exported->issue('error', 'some-code', 'message', 1, 'error'));
        static::assertNull($exported->issue('error', 'some-code', 'message', 4, 'error'));
        static::assertSame(
            'src/doc.php:11: error[parse] Unexpected end of file',
            (string) $exported->issue('error', 'parse', 'Unexpected end of file', 4, 'error'),
        );
    }

    public function testFileRulesAreIgnored(): void
    {
        $exported = new ExportedExample(self::example('final class Foo {}'));

        static::assertNull($exported->issue('warning', 'single-class-per-file', 'message', 2, 'warning'));
    }

    /**
     * @param list<string> $setups
     */
    private static function example(string $code, array $setups = []): Example
    {
        $source = new SymbolSource('function', 'Ns\doc', 'doc', 'Ns', '', $setups, 'src/doc.php', 20);

        return new Example($code, new CodeLocation($source, 'Ns\doc', 'src/doc.php', 10, 12, 1));
    }
}
