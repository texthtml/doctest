<?php declare(strict_types=1);

namespace TH\DocTest\Iterator;

use TH\DocTest\Location;

final readonly class Comment
{
    public function __construct(
        public string $text,
        public Location\CodeLocation $location,
    ) {}
}
