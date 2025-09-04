This is an **explicitness parsing** tool that you can run on your PHP projects to analyze the explicitness of your code.

What is explicitness?

Simply put, explicitness refers to the clarity of data flow through your functions. Ideally all of your inputs should be provided through function arguments, and all of your outputs should be returned through function return values.

```php
<?php
function add($a, $b) {
    return $a + $b; // explicit input and output: all values are provided through function arguments and returned through function return values
}
?>
```

If your function accesses global variables not provided through its arguments, that is an implicit input. If your function changes global state without returning a value, that is an implicit output.

```php
<?php

$some_global_number = 10;

function add($a, $b) {
    echo "Adding $a and $b with global number $some_global_number\n"; // implicit output: printing to stdout

    --$some_global_number; // implicit output: decrementing global variable $some_global_number

    return $a + $b + $some_global_number; // implicit input: reading from global variable $some_global_number
}
?>
```

## How does the tool work?

The tool uses nikic's PHP Parser library to parse PHP code and analyze the explicitness of your functions and class methods.

## How do I run the tool?

```
$ php explicitness-checker.php [--verbose] [--strict] [--props] [--exclude=dir] [--include-pattern=pattern] [--exclude-pattern=pattern] ./path/to/your/project
```

### Flags

- `--verbose` or `-v`: Enable verbose output showing detailed analysis progress
- `--strict`: Enable strict mode which detects additional implicit I/O patterns:
  - File I/O operations (file_get_contents, fwrite, etc.)
  - Standard output operations (echo, print, printf, etc.)
- `--props`: Enable property access detection for object-oriented code:
  - Instance property access (`$this->property`)
  - Static property access (`ClassName::$property`)

### Directory and File Filtering

- `--exclude=directory` or `--exclude directory`: Exclude specific directories from analysis
  - Can be used multiple times to exclude multiple directories
  - By default, `vendor/` is excluded
  - Example: `--exclude=tests --exclude=cache`
- `--include-pattern=pattern` or `--include-pattern pattern`: Only analyze files matching the regex pattern
  - Example: `--include-pattern="src/.*\.php$"` to only analyze PHP files in src/
- `--exclude-pattern=pattern` or `--exclude-pattern pattern`: Exclude files matching the regex pattern
  - Example: `--exclude-pattern="test.*\.php$"` to exclude test files

#### Filtering Examples

```bash
# Exclude vendor and tests directories
php explicitness-checker.php --exclude=vendor --exclude=tests ./project

# Only analyze files in src/ directory
php explicitness-checker.php --include-pattern="src/" ./project

# Exclude all test files
php explicitness-checker.php --exclude-pattern="test.*\.php$" ./project

# Combine multiple filters
php explicitness-checker.php --exclude=vendor --exclude-pattern=".*Test\.php$" ./project
```

### Severity Levels and Exit Codes

The tool categorizes violations into three severity levels:

- **Minor** (Exit code 1): Simple output operations
  - `echo`, `print`, `var_dump`, `print_r`
- **Serious** (Exit code 2): Global state access and property violations  
  - Global variables (`global`, `$GLOBALS`)
  - Superglobals (`$_GET`, `$_POST`, `$_SESSION`, etc.)
  - Property access (`$this->property`, `ClassName::$property`) when `--props` is enabled
- **Critical** (Exit code 3): System-level implicit I/O
  - File operations (`file_get_contents`, `fwrite`, etc.)
  - Environment access (`getenv`, `$_ENV`)
  - Time functions (`time`, `date`, `microtime`)
  - Random functions (`rand`, `random_int`)
  - HTTP headers (`header`, `setcookie`)
  - Session functions (`session_start`, `session_id`)
  - Error logging (`error_log`, `trigger_error`)

#### Exit Codes for CI Integration

- **0**: No violations found
- **1**: Only minor violations found
- **2**: Serious violations found (may include minor)
- **3**: Critical violations found (may include serious and minor)

The tool exits with the highest severity level found, making it easy to integrate into CI pipelines with appropriate failure thresholds.

### Example Output

```
$ php explicitness-checker.php ./path/to/your/project

Analyzing ./path/to/your/project

Thinking...

Results:

| File | Line | Function | Implicit Inputs | Implicit Outputs |
|------|------|----------|-----------------|------------------|
| Calculator.php | 12 | add | read from global variable $some_global_number | wrote to global variable $some_global_number |
```
