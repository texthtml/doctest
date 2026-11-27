# doctest PHP

[![CI](https://github.com/texthtml/doctest/actions/workflows/test.yml/badge.svg)](https://github.com/texthtml/doctest/actions/workflows/test.yml)
[![Codecov](https://img.shields.io/codecov/c/github/texthtml/doctest/main)](https://app.codecov.io/gh/texthtml/doctest)
[![Latest Version](https://img.shields.io/packagist/v/texthtml/doctest.svg)](https://packagist.org/packages/texthtml/doctest)
[![PHP](https://img.shields.io/packagist/dependency-v/texthtml/doctest/php)](https://packagist.org/packages/texthtml/doctest)
[![License](https://img.shields.io/packagist/l/texthtml/doctest.svg)](LICENSE)

Test your docblock code examples.

`doctest` looks for php examples in your functions, methods classes & interfaces
comments and execute them to ensure they are correct.

## Why?

Documentation examples rot. The README or docblock snippet that showed how to
use a function gets copied into the docs on day one, then the function changes
and nobody notices the example is now wrong — readers copy it and it doesn't
work.

Because the example lives *next to the code it documents* and is *executed as
part of your test suite*, `doctest` turns that rot into a test failure: change
the behavior without updating the example, and CI goes red. This repository
dogfoods it: [`examples/Ratio.php`](examples/Ratio.php)'s docblock examples run
in CI.

# How to write examples

The simplest way is to add codeblocks in your comments, and use `assert()` to
check for invariants. If, when executing the example there is an exception, the
example will be marked as failed.

```php
/**
 * Compute the factorial of a non-negative $n
 *
 * ```php
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

Pass `--junit <file>` to also write the results as JUnit XML, consumable by
GitLab or GitHub test reporting:

```sh
./bin/doctest --junit report.xml src/
```

## Checking examples with Mago

Running an example only verifies the code paths it executes. `--check` chooses
what doctest does with the examples: `run` them (the default), and/or check
them with [Mago](https://mago.carthage.software): `lint` (`mago lint`) and
`analyze` (`mago analyze`). Values are comma-separated or repeated, and an
example fails when any of the checks fails. Mago issues (wrong argument types,
renamed methods, coding style…) are reported at their line in the documented
file:

```
$ ./bin/doctest --check lint,analyze --static-scan examples/
 [ERROR] Ratio#2 (examples/Ratio.php:10-11)
         Error: examples/Ratio.php:11: error[invalid-property-write] Cannot
         initialize readonly property `Ratio::$value` from within `self`.
```

```sh
./bin/doctest --check analyze src/            # Mago's analyzer only
./bin/doctest --check run,lint,analyze src/   # run the examples and check them with Mago
```

- Mago runs with the project's `mago.toml` (rules, `php-version`, and
  `minimum-fail-level`: issues below it don't fail an example, like they don't
  fail `mago lint` / `mago analyze`) and sees the project's sources and
  `vendor`. The documented files are analyzed alongside the examples even when
  they are not part of the Mago sources.
- Mago checks all the examples up front. When they also `run`, an example
  Mago reported issues on fails with them without being executed.
- `self::assert*()` calls are checked against the Webmozart Assert assertions,
  including their type narrowing. Examples with an `ExamplesSetup` may also
  call custom assertions (see `CustomAssertions`), which are not checked.
- An intentional issue can be silenced in the example with a Mago pragma
  comment, e.g. `// @mago-expect analysis:invalid-property-write`.
- `--static-scan` is recommended: Mago checks don't include the code under
  test at all, and running examples only includes it right before them.
- Mago is run from `vendor/bin/mago` when installed there (`composer req --dev
  carthage-software/mago`), from the `PATH` otherwise; use `--mago-binary` to
  point to another executable.
