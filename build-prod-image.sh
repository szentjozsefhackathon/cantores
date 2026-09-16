#!/bin/bash

if [ -n "$(git status --porcelain)" ]; then
    echo "⚠️  Warning: You have uncommitted changes. Build may not be reproducible."
fi

# Exit on error
set -e

# Load deployment configuration
if [ -f .env.deploy ]; then
    source .env.deploy
else
    echo "Warning: .env.deploy not found. Using default values or interactive authentication."
fi

# Default values if not set in .env.deploy
DEPLOY_SERVER=${DEPLOY_SERVER:-cantores.hu}
DEPLOY_PORT=${DEPLOY_PORT:-22}
DEPLOY_USER=${DEPLOY_USER:-deploy}
DEPLOY_REMOTE_PATH=${DEPLOY_REMOTE_PATH:-/tmp/}
SSH_KEY_PATH=${SSH_KEY_PATH:-~/.ssh/deploy}

if git rev-parse --git-dir > /dev/null 2>&1; then
    GIT_SHORT_HASH=$(git rev-parse --short HEAD)
    GIT_FULL_HASH=$(git rev-parse HEAD)
else
    GIT_SHORT_HASH="unknown"
    GIT_FULL_HASH="unknown"
    echo "Warning: Not in a git repository. Using 'unknown' for hash."
fi

DIR=dist
mkdir -p $DIR/deploy/production

FILE_PATH=$DIR/creshu-app-prod-$GIT_SHORT_HASH.tar.gz
echo "Build target: $FILE_PATH"
echo "Git commit: $GIT_SHORT_HASH ($GIT_FULL_HASH)"
echo

# Check if image already exists for this commit
if [ -f "$FILE_PATH" ]; then
    echo "✅ Image already built for commit $GIT_SHORT_HASH."
    echo "Skipping Docker build."
else
    echo "🔄 Building Docker image for commit $GIT_SHORT_HASH..."
    
    # Build with git hash as build argument. GITHUB_TOKEN (set in .env.deploy)
    # lets Composer fetch signed GitHub zipballs instead of the unauthenticated
    # codeload legacy.zip redirect, which intermittently returns HTTP 400.
    APP_DOMAIN=cantores.hu GIT_COMMIT_HASH=$GIT_FULL_HASH GITHUB_TOKEN="${GITHUB_TOKEN:-}" docker compose -f docker-compose.prod.yml build app

    # The renderer image derives from the app image just built, so it has to
    # follow it rather than build in parallel.
    echo "🔄 Building MuseScore renderer image..."
    docker build -f Dockerfile.musescore -t creshu-musescore-prod:latest .

    # Both images in one archive: they share every base layer, so the renderer
    # costs only MuseScore and poppler on the wire, and one 'docker load' on the
    # server picks up both.
    docker save creshu-app-prod:latest creshu-musescore-prod:latest | gzip > "$FILE_PATH"

    echo "✅ Docker images saved to $FILE_PATH"
fi

echo
FILE_SIZE=$(stat -c%s "$FILE_PATH" 2>/dev/null || echo "0")
echo "File size: $(numfmt --to=iec $FILE_SIZE)"
echo

# Smoke-test the image before it goes anywhere.
#
# The suite cannot do this. Pest runs the application, not the container, so an
# ARG declared in the wrong scope, an entrypoint that leaves a shell as PID 1 so
# SIGTERM never reaches Octane, a server that cannot bind its port unprivileged,
# or a security header lost with the Apache vhost are all invisible to it. Each
# of those ships silently and surfaces on a deploy; two of them did. This runs
# the real image against a throwaway Postgres and Redis and refuses to upload if
# anything it checks is wrong.
#
# It runs on the cached path too, because "already built for this commit" is not
# the same as "known good" — the tarball may predate the checks. If the image
# itself has been pruned since, it is restored from the tarball rather than
# rebuilt, so what gets tested is exactly what would be uploaded.
#
# SKIP_SMOKE=1 bypasses it, for when the fault is in the harness rather than the
# image — a port collision, or a box without the memory for a second Postgres.
if [ "${SKIP_SMOKE:-0}" = "1" ]; then
    echo "⚠️  SKIP_SMOKE=1 — uploading an image that has not been smoke-tested."
else
    # Both images, because the smoke test renders a score through the renderer
    # as well as driving the app.
    if ! docker image inspect creshu-app-prod >/dev/null 2>&1 ||
       ! docker image inspect creshu-musescore-prod >/dev/null 2>&1; then
        echo "Restoring the images from $FILE_PATH to test them..."
        gunzip -c "$FILE_PATH" | docker load
    fi

    echo "🔬 Smoke-testing the image before upload..."
    if ./smoke-prod-image.sh --no-build; then
        echo "✅ Smoke test passed"
    else
        echo
        echo "❌ Smoke test failed — nothing was uploaded."
        echo "   The tarball is still at $FILE_PATH; fix the image and run again."
        echo "   Re-run the checks on their own with: ./smoke-prod-image.sh --no-build"
        exit 1
    fi
fi
echo

# Upload to remote server
echo "Uploading to $DEPLOY_SERVER:$DEPLOY_PORT..."
echo "Remote path: $DEPLOY_REMOTE_PATH"

if [ -f "$SSH_KEY_PATH" ]; then
    SCP_CMD="scp -P $DEPLOY_PORT -i $SSH_KEY_PATH $FILE_PATH $DEPLOY_USER@$DEPLOY_SERVER:$DEPLOY_REMOTE_PATH"
else
    SCP_CMD="scp -P $DEPLOY_PORT $FILE_PATH $DEPLOY_USER@$DEPLOY_SERVER:$DEPLOY_REMOTE_PATH"
fi

echo "Executing: $SCP_CMD"
$SCP_CMD

if [ $? -eq 0 ]; then
    echo "✅ Upload successful!"
    echo "File uploaded to: $DEPLOY_SERVER:$DEPLOY_REMOTE_PATH$(basename $FILE_PATH)"
else
    echo "❌ Upload failed with exit code: $?"
    exit 1
fi

ls -t dist/creshu-app-prod-*.tar.gz | tail -n +6 | xargs rm -f 2>/dev/null || true

