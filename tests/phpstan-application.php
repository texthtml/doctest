<?php declare(strict_types=1);

use Symfony\Component\Console\Application;
use TH\DocTest\Application as DocTestApplication;

require __DIR__ . "/../vendor/autoload.php";

$application = new Application();

$application->add(new DocTestApplication());

return $application;
