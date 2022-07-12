<?php declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Event;

final class Summary implements EventSubscriberInterface
{
    private StyleInterface $style;
    private int $numberOfSuccesses = 0;
    private int $numberOfFailures = 0;

    public function __construct(
        InputInterface $input,
        OutputInterface $output,
    ) {
        $this->style = new SymfonyStyle($input, $output);
    }

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Event\BeforeTestSuite::class => "initialize",
            Event\AfterTestSuccess::class => "countSuccess",
            Event\AfterTestFailure::class => "countFailure",
            Event\AfterTestSuite::class => "printSummary",
        ];
    }

    public function initialize(Event\BeforeTestSuite $event): void
    {
        $this->numberOfSuccesses = 0;
        $this->numberOfFailures = 0;
    }

    public function countSuccess(): void
    {
        $this->numberOfSuccesses++;
    }

    public function countFailure(): void
    {
        $this->numberOfFailures++;
    }

    public function printSummary(Event\AfterTestSuite $event): void
    {
        if ($event->success) {
            $this->style->success("All tests succeeded ({$this->numberOfSuccesses})");

            return;
        }

        $this->style->success("Number of success: {$this->numberOfSuccesses}");
        $this->style->error("Number of failures: {$this->numberOfFailures}");
    }
}
