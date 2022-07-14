<?php // Exception Expected output to be "This example will fail". Got: ""

// @prints This example will fail

// @throws RuntimeException This example will fail
throw new \RuntimeException("This example will fail");
