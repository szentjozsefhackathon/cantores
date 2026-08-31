#!/bin/bash

# Loads a pg_dump file into the local development database.
# Usage: ./load-prod-db.sh <dump-file>

set -e

DUMP_FILE="${1:?Usage: $0 <dump-file>}"

if [ ! -f "$DUMP_FILE" ]; then
    echo "Error: dump file not found: $DUMP_FILE"
    exit 1
fi

if [ -f .env ]; then
    LOCAL_DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"'"'"'')
    LOCAL_DB_USER=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"'"'"'')
fi

LOCAL_DB_CONTAINER=${LOCAL_DB_CONTAINER:-creshu-database-1}
LOCAL_DB_NAME=${LOCAL_DB_NAME:-cantores}
LOCAL_DB_USER=${LOCAL_DB_USER:-cantor}

echo "=== Load Database Dump ==="
echo "File      : $DUMP_FILE"
echo "Size      : $(du -sh "$DUMP_FILE" | cut -f1)"
echo "Target DB : $LOCAL_DB_NAME (container: $LOCAL_DB_CONTAINER)"
echo

echo "1. Dropping and recreating local database..."
docker exec -i "$LOCAL_DB_CONTAINER" \
    psql -U "$LOCAL_DB_USER" -d postgres \
    -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$LOCAL_DB_NAME' AND pid <> pg_backend_pid();" \
    -c "DROP DATABASE IF EXISTS $LOCAL_DB_NAME;" \
    -c "CREATE DATABASE $LOCAL_DB_NAME OWNER $LOCAL_DB_USER;"
echo "   Done"

echo "2. Restoring dump..."
docker exec -i "$LOCAL_DB_CONTAINER" \
    pg_restore -U "$LOCAL_DB_USER" -d "$LOCAL_DB_NAME" --no-owner --role="$LOCAL_DB_USER" \
    < "$DUMP_FILE"
echo "   Done"

echo
echo "=== Production database loaded successfully ==="
