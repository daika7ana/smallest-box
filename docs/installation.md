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

Run the test suite:

```bash
./vendor/bin/phpunit
```

Run the examples:

```bash
php examples/basic_usage.php
php examples/fluent_api.php
php examples/algorithm_comparison.php
```

## Autoloading

The package uses PSR-4 autoloading. The root namespace is `Daika7ana\SmallestBox\` mapped to the `src/` directory. No additional configuration is required when installed via Composer.
