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

    public function run(bool $bail): TestOutcome
    {
        $this->eventDispatcher->dispatch(new Event\BeforeTestSuite());

        $outcome = TestOutcome::Success;

        try {
            foreach ($this->examples as $example) {
                $outcome = TestOutcome::and(
                    $outcome,
                    $this->runExample($example),
                );

                if ($bail && $outcome->isFailure()) {
                    return $outcome;
                }
            }

            return $outcome;
        } finally {
            $this->eventDispatcher->dispatch(new Event\AfterTestSuite(outcome: $outcome));
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

    private function runExample(Example $example): TestOutcome
    {
        try {
            $this->eventDispatcher->dispatch(new Event\BeforeTest($example));
            $this->eventDispatcher->dispatch(new Event\ExecuteTest($example));
            $this->eventDispatcher->dispatch(new Event\AfterTest($example));
            $this->eventDispatcher->dispatch(new Event\AfterTestSuccess($example));

            return TestOutcome::Success;
        } catch (\Throwable $th) {
            $this->eventDispatcher->dispatch(new Event\AfterTest($example));
            $this->eventDispatcher->dispatch(new Event\AfterTestFailure($example, $th));

            return TestOutcome::Failure;
        }
    }
}
