<?php

declare(strict_types=1);

namespace TH\DocTest\Tests\Iterator\Fixtures;

final class ClassLevelSetups
{
    public static function methodSetupWasInstantiated(): bool
    {
        return self::$instantiatedFrom === 'method';
    }

    public static function classSetupWasInstantiated(): bool
    {
        return self::$instantiatedFrom === 'class';
    }

    public static string $instantiatedFrom = '';
}

final class MethodLevelSetup
{
    public function __construct()
    {
        ClassLevelSetups::$instantiatedFrom = 'method';
    }
}

final class ClassLevelSetup
{
    public function __construct()
    {
        ClassLevelSetups::$instantiatedFrom = 'class';
    }
}
