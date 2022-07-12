<?php declare(strict_types=1);

namespace TH\DocTest;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Iterator\Examples;
use TH\DocTest\Iterator\FilteredExamples;

final class TestSuite
{
    private EventDispatcher $eventDispatcher;

    public function __construct(
        private readonly Examples $examples,
    ) {
        $this->eventDispatcher = new EventDispatcher();
    }

    public function run(bool $bail): bool
    {
        $this->eventDispatcher->dispatch(new Event\BeforeTestSuite());

        try {
            foreach ($this->examples as $example) {
                try {
                    $this->eventDispatcher->dispatch(new Event\BeforeTest($example));
                    $this->eventDispatcher->dispatch(new Event\ExecuteTest($example));
                    $this->eventDispatcher->dispatch(new Event\AfterTest($example));
                    $this->eventDispatcher->dispatch(new Event\AfterTestSuccess($example));
                } catch (\Throwable $th) {
                    $this->eventDispatcher->dispatch(new Event\AfterTest($example));
                    $this->eventDispatcher->dispatch(new Event\AfterTestFailure($example, $th));

                    if ($bail) {
                        return false;
                    }
                }
            }

            return !isset($th);
        } finally {
            $this->eventDispatcher->dispatch(new Event\AfterTestSuite(success: !isset($th)));
        }
    }

    public function addSubscriber(EventSubscriberInterface $eventSubscriber): void
    {
        $this->eventDispatcher->addSubscriber($eventSubscriber);
    }

    /**
     * @param array<string> $paths paths to files and folder to look for PHP comments code examples in
     */
    public static function fromPaths(array $paths, string $filter): self
    {
        return new self(FilteredExamples::fromPaths($paths, $filter));
    }
}
