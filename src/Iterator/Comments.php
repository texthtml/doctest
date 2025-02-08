<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\TestCase;

/**
 * @extends \IteratorAggregate<Comment|TestCase\SourceError>
 */
interface Comments extends \IteratorAggregate
{
}
