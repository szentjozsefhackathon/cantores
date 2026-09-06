#!/bin/bash

# Copies the rendered score incipits from production into the local storage dir.
# Reads connection details from .env.deploy (same file used by deploy-prod.sh).
#
# Incipits are PNGs keyed by score id (storage/app/private/incipits/{id}.png).
# They are produced in the browser by the score editor and uploaded, so there is
# no server-side job that can regenerate them locally -- after loading a
# production dump they have to be copied across too, or every score falls back
# to "no incipit".
#
# Stale local files are removed first on purpose: score ids in the restored dump
# are production's, so a leftover incipits/42.png from an older local database
# would silently render the wrong music under a score that never had one.
#
# Run this from the WSL host, NOT from inside the devcontainer: it needs the
# deploy SSH key. Uploaded score files (score-files/) are deliberately not
# copied -- they are encrypted with the production APP_KEY and would not decrypt
# against a local one.
#
# Usage: ./pull-prod-incipits.sh [--force]

set -e

FORCE=0
if [ "$1" = "--force" ]; then
    FORCE=1
fi

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

LOCAL_DIR="storage/app/private/incipits"
REMOTE_DIR="/var/www/html/storage/app/private"

echo "=== Pull Production Incipits ==="
echo "Server : $DEPLOY_SERVER:$DEPLOY_PORT"
echo "Target : $LOCAL_DIR"
echo "Local  : $(ls "$LOCAL_DIR" 2>/dev/null | wc -l) file(s) currently present"
echo

if [ "$FORCE" -ne 1 ]; then
    read -r -p "Replace the local incipits with production's? [y/N] " REPLY
    case "$REPLY" in
        [yY]|[yY][eE][sS]) ;;
        *) echo "Cancelled."; exit 0 ;;
    esac
    echo
fi

echo "1. Removing stale local incipits..."
rm -rf "${LOCAL_DIR:?}"
mkdir -p "$LOCAL_DIR"

echo "2. Streaming incipits from production..."
ssh $SSH_OPTS "$SSH_TARGET" \
    "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml exec -T app \
     tar -cf - -C $REMOTE_DIR incipits" \
    | tar -xf - -C storage/app/private

echo
echo "=== Pull complete ==="
echo "Files  : $(ls "$LOCAL_DIR" 2>/dev/null | wc -l)"
echo "Size   : $(du -sh "$LOCAL_DIR" 2>/dev/null | cut -f1)"
