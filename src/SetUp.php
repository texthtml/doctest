<?php

declare(strict_types=1);

namespace TH\DocTest;

/**
 * Marker interface for classes used with the {@see Attributes\ExamplesSetup} attribute.
 *
 * Instances are created fresh before each example and discarded after it: everything
 * belongs in the constructor, which must not require any argument.
 */
interface SetUp {}
