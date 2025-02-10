<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\Location;

final class Comment
{
    public function __construct(
        public readonly string $text,
        public readonly Location\CodeLocation $location,
    ) {}
}
