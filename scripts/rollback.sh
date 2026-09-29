#!/usr/bin/env bash
# ==============================================================================
# Tech-Tians CRM - Atomic Release Rollback Script
#
# Usage:
#   ./scripts/rollback.sh            # Roll back to the immediately preceding release
#   ./scripts/rollback.sh <release>  # Roll back to a specific release timestamp
# ==============================================================================

set -euo pipefail

# Determine base deployment path (parent directory of scripts/ or current working directory)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_PATH="${DEPLOY_PATH:-$(cd "$SCRIPT_DIR/.." && pwd)}"
RELEASES_DIR="$DEPLOY_PATH/releases"
CURRENT_LINK="$DEPLOY_PATH/current"

echo "=================================================="
echo " CRM Release Rollback Tool"
echo " Base Path: $DEPLOY_PATH"
echo "=================================================="

if [ ! -d "$RELEASES_DIR" ]; then
    echo "ERROR: Releases directory not found at: $RELEASES_DIR" >&2
    exit 1
fi

# Determine currently active release
CURRENT_TARGET=""
if [ -L "$CURRENT_LINK" ]; then
    CURRENT_TARGET="$(readlink "$CURRENT_LINK")"
    echo "Currently active release: $(basename "$CURRENT_TARGET")"
else
    echo "WARNING: No existing 'current' symlink found at $CURRENT_LINK"
fi

TARGET_RELEASE=""

# Check if a specific target release timestamp was supplied as an argument
if [ $# -ge 1 ] && [ -n "$1" ]; then
    REQUESTED_TARGET="$RELEASES_DIR/$1"
    if [ ! -d "$REQUESTED_TARGET" ]; then
        echo "ERROR: Requested release '$1' does not exist in $RELEASES_DIR" >&2
        echo "Available releases:"
        ls -1 "$RELEASES_DIR"
        exit 1
    fi
    TARGET_RELEASE="$REQUESTED_TARGET"
else
    # Find all releases sorted newest first
    AVAILABLE_RELEASES=($(ls -1dt "$RELEASES_DIR"/* 2>/dev/null || true))
    TOTAL_RELEASES=${#AVAILABLE_RELEASES[@]}

    if [ "$TOTAL_RELEASES" -lt 2 ]; then
        echo "ERROR: Cannot roll back. Fewer than 2 releases found in $RELEASES_DIR." >&2
        exit 1
    fi

    # Find the first release in the list that is NOT the currently active release
    for rel in "${AVAILABLE_RELEASES[@]}"; do
        if [ "$rel" != "$CURRENT_TARGET" ]; then
            TARGET_RELEASE="$rel"
            break
        fi
    done

    # Fallback to second newest if current couldn't be resolved
    if [ -z "$TARGET_RELEASE" ] && [ "$TOTAL_RELEASES" -ge 2 ]; then
        TARGET_RELEASE="${AVAILABLE_RELEASES[1]}"
    fi
fi

if [ -z "$TARGET_RELEASE" ] || [ ! -d "$TARGET_RELEASE" ]; then
    echo "ERROR: Failed to identify a valid rollback release target." >&2
    exit 1
fi

TARGET_NAME="$(basename "$TARGET_RELEASE")"
echo "Rolling back to release: $TARGET_NAME"

# Atomically switch symlink
ln -sfn "$TARGET_RELEASE" "$CURRENT_LINK"
echo "Symlink updated: $CURRENT_LINK -> $TARGET_RELEASE"

# Reload PHP-FPM to flush OPcache bytecode
echo "Reloading PHP-FPM to invalidate OPcache..."
if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload php8.2-fpm || sudo systemctl restart php8.2-fpm || echo "Notice: systemctl reload php8.2-fpm returned non-zero (check sudoers)."
elif command -v service >/dev/null 2>&1; then
    sudo service php8.2-fpm reload || true
fi

echo "=================================================="
echo " Rollback completed successfully!"
echo " Active release is now: $TARGET_NAME"
echo "=================================================="
