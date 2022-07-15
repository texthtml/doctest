<?php declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Event;

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
        $this->output->success((string) $event->example->location);
    }

    public function printFailure(Event\AfterTestFailure $event): void
    {
        $error = [$event->example->location];

        if ($this->output->isVerbose()) {
            $error[] = $event->example->code;
        }

        $error[] = $this->output->isVeryVerbose()
            ? $event->failure
            : $event->failure->getMessage();

        $this->output->error($error);
    }
}
