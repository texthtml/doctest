<?php // Exception Expected no output. Got: "This example will fail"

echo "This example will fail\n";

// @throws RuntimeException This example will fail
throw new \RuntimeException("This example will fail");
