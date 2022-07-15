# doctest PHP

Test your docblock code examples.

`doctest` looks for php examples in your functions, methods classes & interfaces
comments and execute them to ensure they are correct.

# How to write examples

The simplest way is to add codeblocks in your comments, and use `assert()` to
check for invariants. If, when executing the example there is an exception, the
example will be marked as failed.

```php
/**
 * Compute the factorial of a non-negative $n
 *
 * ```
 * assert(factorial(0) === 1);
 * assert(factorial(5) === 120);
 * ```
 */
function factorial(int $n): int {
    if ($n === 0) {
        return 1;
    }

    if ($n < 0) {
        throw new \InvalidArgumentException("unexpected negative integer: $n");
    }

    return $n * factorial($n - 1);
}
```

## Assertions

One easy way to throw exceptions when something is unexpected is to use
[`assert()`](https://php.net/assert). But to make it nicer to write assertions
and have better error messages automatically, you can call [the assertion helpers
from webmozart/assert](https://packagist.org/packages/webmozart/assert#user-content-assertions)
with the `self::assert*` shortcut:

```php
self::assertEq(factorial(0), 1);
self::assertEq(factorial(5), 120);
```

### Testing exceptions

Sometimes we want to show that some code *will* throw an exception, we can do
that by adding an inline comment anywhere in the example with the following
format: `// @throws [exception class] [exception message]`

```php
// @throws InvalidArgumentException unexpected negative integer: -10
factorial(-10);
```

Note: code after the exception won't be executed so any in-code assertions
coming after the first exception won't be verified.

### Testing output

By default `doctest` will make an example fail if it produce any output unless
it's documented with one or more `@prints` inline comments anywhere in the
example with the following format:
`// @prints [text]. That's to make sure that any output produced is expected.

```php
echo factorial(6); // @prints 720
echo factorial(10);
// the @prints annotations can be anywhere, only their order is important, not their exact positions
// @prints 3628800
```

# Installation

```sh
composer req --dev texthtml/doctest
```

# Usage

Simply run the `doctest` command and it will look for examples to test in your
`src/` folder. Call `doctest --help` for customizing where to look for examples
and other options.

```
$ ./bin/doctest examples/factorial.php
 [OK] factorial#1 (examples/factorial.php:7)
 [OK] factorial#2 (examples/factorial.php:14)
 [ERROR] factorial#3 (examples/factorial.php:20)
         Expected output to be "". Got: "720"
 [OK] factorial#4 (examples/factorial.php:25)
 [ERROR] factorial#5 (examples/factorial.php:30)
         unexpected negative integer: -10
 [OK] factorial#6 (examples/factorial.php:35)
 [ERROR] factorial#7 (examples/factorial.php:43)
         Expected a value equal to 3628890. Got: 3628800
 [OK] Number of success: 4
 [ERROR] Number of failures: 3
```

# TODO

* Junit output
* Find PHP examples in markdown
