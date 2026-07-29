#!/usr/bin/env bash
set -euo pipefail

# Import Bluesky product names/SKUs into OMS (local or live server).
#
# Usage:
#   ./scripts/import_bluesky_products.sh "/path/to/BLUESKY PRODUCT NAME.xlsx"
#   ./scripts/import_bluesky_products.sh "/path/to/file.xlsx" --dry-run
#   ./scripts/import_bluesky_products.sh "/path/to/file.xlsx" --update
#
# Live server example (SSH into production first):
#   cd /var/www/oms
#   ./scripts/import_bluesky_products.sh "/tmp/BLUESKY PRODUCT NAME.xlsx" --dry-run
#   ./scripts/import_bluesky_products.sh "/tmp/BLUESKY PRODUCT NAME.xlsx" --update
#
# Or upload from your Mac in one step:
#   scp "/Users/jackiets/Downloads/BLUESKY PRODUCT NAME.xlsx" user@your-server:/tmp/
#   ssh user@your-server 'cd /var/www/oms && ./scripts/import_bluesky_products.sh "/tmp/BLUESKY PRODUCT NAME.xlsx" --update'

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <excel-file> [--dry-run] [--update] [--default-price=0]" >&2
  exit 1
fi

FILE="$1"
shift

if [[ ! -f "$FILE" ]]; then
  echo "File not found: $FILE" >&2
  exit 1
fi

echo "==> Bluesky product import"
echo "    App:  $ROOT_DIR"
echo "    File: $FILE"
echo "    Args: $*"
echo

php artisan products:import-bluesky "$FILE" "$@"
