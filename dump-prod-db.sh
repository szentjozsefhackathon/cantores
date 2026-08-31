#!/bin/bash

# Downloads a pg_dump from the production database to a local file.
# Reads connection details from .env.deploy (same file used by deploy-prod.sh).
# Prints the path of the saved dump file on completion.

set -e

if [ -f .env.deploy ]; then
    source .env.deploy
else
    echo "Error: .env.deploy not found."
    exit 1
fi

DEPLOY_SERVER=${DEPLOY_SERVER:-cantores.hu}
DEPLOY_PORT=${DEPLOY_PORT:-22}
DEPLOY_USER=${DEPLOY_USER:-briff}
DEPLOY_REMOTE_PATH=${DEPLOY_REMOTE_PATH:-/home/briff/creshu-app}
SSH_KEY_PATH=${SSH_KEY_PATH:-~/.ssh/deploy}

SSH_OPTS="-p $DEPLOY_PORT"
if [ -f "$SSH_KEY_PATH" ]; then
    SSH_OPTS="$SSH_OPTS -i $SSH_KEY_PATH"
fi
SSH_TARGET="$DEPLOY_USER@$DEPLOY_SERVER"

DUMP_FILE="/tmp/prod-creshu-$(date +%Y%m%d-%H%M%S).dump"

echo "=== Dump Production Database ==="
echo "Server : $DEPLOY_SERVER:$DEPLOY_PORT"
echo "Path   : $DEPLOY_REMOTE_PATH"
echo

echo "Dumping production database..."
ssh $SSH_OPTS "$SSH_TARGET" \
    "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml exec -T database \
     sh -c 'pg_dump -U \"\$POSTGRES_USER\" -Fc \"\$POSTGRES_DB\"'" \
    > "$DUMP_FILE"

echo
echo "=== Dump complete ==="
echo "File   : $DUMP_FILE"
echo "Size   : $(du -sh "$DUMP_FILE" | cut -f1)"
echo
echo "Load it into local DB with:"
echo "  ./load-prod-db.sh $DUMP_FILE"
