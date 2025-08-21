#!/bin/bash

# A script to generate a self-contained, styled HTML file with the
# PHPUnit code coverage summary table.
#
# Updated for PHPUnit 11 / php-code-coverage 11.

# --- Configuration ---
COVERAGE_DIR="var/coverage"
FINAL_FILE="var/coverage-summary-styled.html"

# --- Script Logic ---

echo "Step 1: Generating full HTML coverage report in '${COVERAGE_DIR}'..."
# Clean up previous reports to ensure a fresh run
rm -rf "$COVERAGE_DIR"
php bin/phpunit --coverage-html "$COVERAGE_DIR"

if [ $? -ne 0 ]; then
    echo "Error: PHPUnit failed to generate the coverage report."
    exit 1
fi

SOURCE_HTML="$COVERAGE_DIR/index.html"
CSS_FILE_1="$COVERAGE_DIR/_css/bootstrap.min.css"
CSS_FILE_2="$COVERAGE_DIR/_css/style.css"

if [ ! -f "$SOURCE_HTML" ] || [ ! -f "$CSS_FILE_1" ] || [ ! -f "$CSS_FILE_2" ]; then
    echo "Error: Necessary coverage files were not found in '${COVERAGE_DIR}'."
    echo "Please check the output of PHPUnit."
    exit 1
fi

echo "Step 2: Extracting summary table from the report..."
# This updated awk command is more robust for PHPUnit 11 reports.
# It finds the table within the "<div class="table-responsive">" block.
TABLE_HTML=$(awk '
  /class="table-responsive"/ { in_div=1 }
  in_div && /<table/ { in_table=1 }
  in_table { print }
  in_table && /<\/table>/ { exit }
' "$SOURCE_HTML")

if [ -z "$TABLE_HTML" ]; then
    echo "Error: Could not extract the summary table from '${SOURCE_HTML}'."
    echo "This script may need updating for future PHPUnit versions."
    exit 1
fi

echo "Step 3: Assembling the self-contained HTML file..."
mkdir -p "$(dirname "$FINAL_FILE")"

# This part remains the same: it builds the final HTML by inlining CSS and the table.
cat > "$FINAL_FILE" <<EOF
        ${TABLE_HTML}
EOF

if [ -s "$FINAL_FILE" ]; then
    echo "--------------------------------------------------------"
    echo "Success! Self-contained colored summary created at:"
    echo "=> ${FINAL_FILE}"
    echo "--------------------------------------------------------"
    exit 0
else
    echo "Error: Failed to create the final HTML file."
    exit 1
fi
