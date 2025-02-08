<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\Location;

/**
 * @extends \IteratorAggregate<Location,string>
 */
interface Comments extends \IteratorAggregate
{
}
