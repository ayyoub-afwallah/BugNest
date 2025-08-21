#!/bin/bash

# Paths
HTML_DIR="var/coverage"
HTML_FILE="$HTML_DIR/coverage-summary-styled.html"
IMG_FILE="$HTML_DIR/coverage.png"
README_FILE="README.md"

# 1️⃣ Run PHPUnit HTML coverage
echo "Generating HTML coverage..."
php bin/phpunit --coverage-html "$HTML_DIR"

# 2️⃣ Convert HTML to PNG using wkhtmltoimage
echo "Converting HTML to PNG..."
wkhtmltoimage --width 1920 --height 3000 "$HTML_FILE" "$IMG_FILE"

# 3️⃣ Update README.md
echo "Updating README.md..."
# Remove old coverage image if exists
sed -i '/!\[Code Coverage\]/d' "$README_FILE"
# Add new coverage image at the top
sed -i "1i ![Code Coverage]($IMG_FILE)" "$README_FILE"

echo "Done! Coverage image generated and README.md updated."
