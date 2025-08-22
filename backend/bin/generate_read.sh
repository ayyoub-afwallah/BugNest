#!/bin/bash

# A direct and robust script to generate a README.md by injecting content
# from generated files into a template. This script uses awk for reliable
# multi-line replacements.

set -e # Exit immediately if a command fails.

# --- Configuration ---
TEMPLATE_FILE="README.template.md"
OUTPUT_FILE="README.md"

# Temporary files to hold the generated content
MERMAID_CONTENT_FILE="env_diagram.md"
COVERAGE_CONTENT_FILE="coverage-diagram.mmd"
COVERAGE_CONTENT_FILE_CONTENT="coverage-diagram.mmd"

# Scripts that generate the content
SYMFONY_MERMAID_COMMAND="app:readme-generate" # This command should ONLY output the mermaid block
COVERAGE_GENERATION_SCRIPT="./generate-coverage-summary.sh"

# Placeholders to find in the template
MERMAID_PLACEHOLDER="%%MERMAID_DIAGRAM%%"
COVERAGE_PLACEHOLDER="%%COVERAGE_SUMMARY%%"


# --- Script Logic ---

echo "--- Starting README Generation ---"

# --- Step 1: Generate the content files ---

echo "➡️ Step 1 of 3: Generating Mermaid diagram content..."
# IMPORTANT: Ensure your Symfony command outputs ONLY the mermaid code block.
# Redirecting the output of your existing command to a file.
php bin/console app:generate-mermaid | awk '/^```mermaid/,/^```/' > "$MERMAID_CONTENT_FILE"
if [ ! -s "$MERMAID_CONTENT_FILE" ]; then
    echo "❌ FAILED: The Mermaid content file is empty. Check the Symfony command."
    exit 1
fi
echo "✅ Mermaid content saved to '$MERMAID_CONTENT_FILE'."

echo "➡️ Step 2 of 3: Generating Test Coverage diagram content..."

php bin/console coverage:mermaid var/coverage/clover.xml

# --- Step 3: Assemble the final README.md ---

echo "➡️ Step 3 of 3: Assembling the final README.md from template..."

# This awk command is the core of the solution. It reads the template file
# and replaces placeholders with the content of the other files.
awk \
  -v mermaid_file="$MERMAID_CONTENT_FILE" \
  -v coverage_file="$COVERAGE_CONTENT_FILE" \
  -v mermaid_placeholder="$MERMAID_PLACEHOLDER" \
  -v coverage_placeholder="$COVERAGE_PLACEHOLDER" \
'
  {
    if ($0 ~ mermaid_placeholder) {
      while ((getline line < mermaid_file) > 0) {
        print line
      }
      close(mermaid_file)
    } else if ($0 ~ coverage_placeholder) {
      while ((getline line < coverage_file) > 0) {
        print line
      }
      close(coverage_file)
    } else {
      print
    }
  }
' "$TEMPLATE_FILE" > "$OUTPUT_FILE"


# --- Final Check and Success Message ---
if [ -s "$OUTPUT_FILE" ]; then
    echo "--------------------------------------------------------"
    echo "🎉 SUCCESS! The README.md file has been generated."
    echo "--------------------------------------------------------"
    exit 0
else
    echo "❌ FAILED: The final README.md is empty. An unknown error occurred."
    exit 1
fi
