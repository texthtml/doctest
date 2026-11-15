<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Iterator\Fixtures;

use TH\DocTest\Attributes\ExamplesSetup;

#[ExamplesSetup(new ClassLevelSetup())]
final class ClassLevelSetupsFixture
{
    /**
     * # Examples
     *
     * ```php
     * assert(ClassLevelSetups::classSetupWasInstantiated());
     * ```
     */
    #[ExamplesSetup(new MethodLevelSetup())]
    public static function greet(): string
    {
        return 'hello';
    }

    /**
     * # Examples
     *
     * ```php
     * assert(ClassLevelSetups::classSetupWasInstantiated());
     * ```
     */
    public static function wave(): string
    {
        return 'hi';
    }
}
