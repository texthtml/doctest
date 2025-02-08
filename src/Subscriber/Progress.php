<?php declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Event;
use TH\DocTest\Location\CodeLocation;
use TH\DocTest\TestCase\Example;

final class Progress implements EventSubscriberInterface
{
    private OutputInterface&StyleInterface $output;

    public function __construct(
        InputInterface $input,
        OutputInterface $output,
    ) {
        $this->output = new SymfonyStyle($input, $output);
    }

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Event\AfterTestSuccess::class => "printSuccess",
            Event\AfterTestFailure::class => "printFailure",
        ];
    }

    public function printSuccess(Event\AfterTestSuccess $event): void
    {
        $this->output->success((string) $event->test->location());
    }

    public function printFailure(Event\AfterTestFailure $event): void
    {
        $test = $event->test;
        $location = $test->location();

        if ($location instanceof CodeLocation) {
            $location = $this->line($location, $event->failure);
        }

        $error = [$location];

        $verboseError = $this->output->isVerbose();

        if ($test instanceof Example) {
            if ($this->output->isVerbose()) {
                $error[] = "```" . PHP_EOL . $test->code . PHP_EOL . "```";
            }

            $verboseError = $this->output->isVeryVerbose();
        }

        $error[] = $verboseError
            ? $event->failure
            : "Error: {$event->failure->getMessage()}";

        $this->output->error($error);
    }

    private function line(CodeLocation $location, \Throwable $th): CodeLocation
    {
        foreach (self::lines($th) as $file => $line) {
            if (\str_ends_with($file, "eval()'d code")) {
                return $location->startingAt($line - 1)->ofLength(1);
            }
        }

        return $location;
    }

    /**
     * @return \Traversable<string,int>
     */
    private function lines(\Throwable $th): \Traversable
    {
        $file = $th->getFile();

        if (\str_starts_with($file, \substr(__FILE__, 0, -23) . "TestCase/Example.php(")) {
            yield $file => $th->getLine();
        }

        foreach ($th->getTrace() as $line) {
            if (isset($line["line"], $line["class"], $line["file"]) && $line["class"] === Example::class) {
                yield $line["file"] => $line["line"];
            }
        }
    }
}
