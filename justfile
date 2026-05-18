# List available recipes
default:
    @just --list

# Install dependencies
install:
    composer install

# Update dependencies
update:
    composer update

# Fix code style
lint:
    vendor/bin/pint

# Check code style without fixing
lint-check:
    vendor/bin/pint --test

# Run all checks
check: lint-check
