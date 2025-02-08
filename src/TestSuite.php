<?php declare(strict_types=1);

namespace TH\DocTest;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TH\DocTest\Iterator\FilteredTests;
use TH\DocTest\Iterator\Tests;

final class TestSuite
{
    private EventDispatcher $eventDispatcher;

    public function __construct(
        private readonly Tests $tests,
    ) {
        $this->eventDispatcher = new EventDispatcher();
    }

    public function run(bool $bail): TestOutcome
    {
        $this->eventDispatcher->dispatch(new Event\BeforeTestSuite());

        $suiteOutcome = TestOutcome::Success;

        try {
            foreach ($this->tests as $test) {
                $testOutcome = $this->runTest($test);

                $suiteOutcome = $suiteOutcome->and($testOutcome);

                if ($bail && $suiteOutcome->isFailure()) {
                    return $suiteOutcome;
                }
            }

            return $suiteOutcome;
        } finally {
            $this->eventDispatcher->dispatch(new Event\AfterTestSuite($suiteOutcome));
        }
    }

    public function addSubscriber(EventSubscriberInterface $eventSubscriber): void
    {
        $this->eventDispatcher->addSubscriber($eventSubscriber);
    }

    /**
     * @param array<string> $paths paths to files and folder to look for PHP comments code examples in
     * @param list<string>|null $acceptedLanguages Use empty string for unspecified language, and null for any languages
     */
    public static function fromPaths(array $paths, string $filter, ?array $acceptedLanguages): self
    {
        return new self(FilteredTests::fromPaths($paths, $filter, $acceptedLanguages));
    }

    private function runTest(TestCase $test): TestOutcome
    {
        try {
            $this->eventDispatcher->dispatch(new Event\BeforeTest($test));
            $this->eventDispatcher->dispatch(new Event\ExecuteTest($test));
            $this->eventDispatcher->dispatch(new Event\AfterTest($test));
            $this->eventDispatcher->dispatch(new Event\AfterTestSuccess($test));

            return TestOutcome::Success;
        } catch (\Throwable $th) {
            $this->eventDispatcher->dispatch(new Event\AfterTest($test));
            $this->eventDispatcher->dispatch(new Event\AfterTestFailure($test, $th));

            return TestOutcome::Failure;
        }
    }
}
