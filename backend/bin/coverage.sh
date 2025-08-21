#!/bin/bash

# Output directory for HTML coverage
OUTPUT_DIR="var/coverage"

# Run PHPUnit and generate HTML coverage
php bin/phpunit --coverage-html $OUTPUT_DIR

echo "HTML coverage report generated in $OUTPUT_DIR/index.html"
echo "Open it in your browser to see colored coverage."
