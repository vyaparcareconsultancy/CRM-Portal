#!/usr/bin/env bash

# ==============================================================================
# scripts/lint.sh — PHP Syntax Linting Script
# Recursively runs php -l on all PHP files (excluding vendor and cache).
# Exits with non-zero code on any syntax error.
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

echo "Linting all PHP files in project..."

FAILED=0
CHECKED=0

while IFS= read -r file; do
    CHECKED=$((CHECKED + 1))
    if ! OUTPUT=$(php -l "$file" 2>&1); then
        echo "❌ Syntax error in $file:"
        echo "$OUTPUT"
        FAILED=$((FAILED + 1))
    fi
done < <(find app config database cron public tests -type f -name "*.php")

echo "Checked $CHECKED files."

if [ "$FAILED" -gt 0 ]; then
    echo "❌ Linting failed: $FAILED file(s) have syntax errors."
    exit 1
fi

echo "✅ All $CHECKED PHP files passed syntax checks with zero errors."
exit 0
