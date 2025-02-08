<?php declare(strict_types=1);

namespace TH\DocTest\Subscriber;

use Symfony\Component\Console\Helper\ProgressBar as HelperProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Event;

final class ProgressBar implements EventSubscriberInterface
{
    private HelperProgressBar $progressBar;

    public function __construct(
        InputInterface $input,
        OutputInterface $output,
    ) {
        $io = new SymfonyStyle($input, $output);
        $this->progressBar = $io->createProgressBar();

        $sourceFormat = $this->progressBar::FORMAT_NORMAL . "_nomax";
        $format = "doctest_$sourceFormat";

        HelperProgressBar::setFormatDefinition(
            $format,
            $this->progressBar::getFormatDefinition($sourceFormat) . " %message%",
        );

        $this->progressBar->setFormat($format);
    }

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Event\BeforeTestSuite::class => "initialize",
            Event\BeforeTest::class => "advance",
            Event\AfterTest::class => "clear",
            Event\AfterTestSuite::class => "clear",
        ];
    }

    public function initialize(): void
    {
        $this->progressBar->setMessage("");
        $this->progressBar->start();
    }

    public function advance(Event\BeforeTest $event): void
    {
        $this->progressBar->setMessage((string) $event->test->location());
        $this->progressBar->advance();
    }

    public function clear(): void
    {
        $this->progressBar->clear();
    }
}
