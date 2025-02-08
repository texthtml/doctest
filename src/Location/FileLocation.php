<?php declare(strict_types=1);

namespace TH\DocTest\Location;

final class FileLocation implements Location
{
    private function __construct(
        public readonly string $path,
    ) {}

    public static function fromPath(string $path): self
    {
        return new self($path);
    }

    public function __toString(): string
    {
        return $this->path;
    }
}
