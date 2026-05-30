# Installation

## Requirements

- PHP >= 7.4
- Composer

## Install via Composer

```bash
composer require daika7ana/smallest-box
```

## Development Setup

```bash
git clone https://github.com/daika7ana/smallest-box.git
cd smallest-box
composer install
```

### Running Checks

The project includes Composer scripts for code style, static analysis, and tests:

```bash
composer pint    # Check code style (Laravel Pint)
composer stan    # Run static analysis (PHPStan)
composer test    # Run the test suite (PHPUnit)
composer ci      # Run all three in sequence
```

## Autoloading

The package uses PSR-4 autoloading. The root namespace is `Daika7ana\SmallestBox\` mapped to the `src/` directory. No additional configuration is required when installed via Composer.
