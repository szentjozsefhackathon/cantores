#!/bin/bash

# Exit on error
set -e

# Load deployment configuration
if [ -f .env.deploy ]; then
    source .env.deploy
else
    echo "Error: .env.deploy not found. Create it with deployment configuration."
    exit 1
fi

# Default values if not set in .env.deploy
DEPLOY_SERVER=${DEPLOY_SERVER:-cantores.hu}
DEPLOY_PORT=${DEPLOY_PORT:-22}
DEPLOY_USER=${DEPLOY_USER:-deploy}
DEPLOY_REMOTE_PATH=${DEPLOY_REMOTE_PATH:-/tmp/}
SSH_KEY_PATH=${SSH_KEY_PATH:-~/.ssh/deploy}

# Get git short hash for filename
if git rev-parse --git-dir > /dev/null 2>&1; then
    GIT_SHORT_HASH=$(git rev-parse --short HEAD)
else
    GIT_SHORT_HASH="unknown"
    echo "Warning: Not in a git repository. Using 'unknown' for hash."
fi

# Local file paths
DIR=dist
FILE_NAME="creshu-app-prod-$GIT_SHORT_HASH.tar.gz"
LOCAL_FILE_PATH="$DIR/$FILE_NAME"

# Check if the image file exists locally
if [ ! -f "$LOCAL_FILE_PATH" ]; then
    echo "Error: Docker image file not found: $LOCAL_FILE_PATH"
    echo "Run ./build-prod-image.sh first to build and upload the image."
    exit 1
fi

echo "=== Deployment to $DEPLOY_SERVER:$DEPLOY_PORT ==="
echo "Git commit: $GIT_SHORT_HASH"
echo "Local file: $LOCAL_FILE_PATH"
echo "Remote path: $DEPLOY_REMOTE_PATH"
echo

# Build SSH command with optional key
SSH_CMD="ssh -p $DEPLOY_PORT"
if [ -f "$SSH_KEY_PATH" ]; then
    SSH_CMD="$SSH_CMD -i $SSH_KEY_PATH"
fi
SSH_TARGET="$DEPLOY_USER@$DEPLOY_SERVER"

# 1. Upload the Docker image
echo "1. Uploading Docker image..."
SCP_CMD="scp -P $DEPLOY_PORT"
if [ -f "$SSH_KEY_PATH" ]; then
    SCP_CMD="$SCP_CMD -i $SSH_KEY_PATH"
fi

echo "   Executing: $SCP_CMD $LOCAL_FILE_PATH $SSH_TARGET:$DEPLOY_REMOTE_PATH"
$SCP_CMD "$LOCAL_FILE_PATH" "$SSH_TARGET:$DEPLOY_REMOTE_PATH"

if [ $? -ne 0 ]; then
    echo "❌ Upload failed!"
    exit 1
fi
echo "   ✅ Upload successful"

# 2. Load Docker image on server
echo "2. Loading Docker image on server..."
REMOTE_FILE_PATH="$DEPLOY_REMOTE_PATH/$FILE_NAME"
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker load -i $FILE_NAME"

if [ $? -ne 0 ]; then
    echo "❌ Docker load failed!"
    exit 1
fi
echo "   ✅ Docker image loaded"

# 3. Check for .env.prod on server
echo "3. Checking for environment configuration..."
ENV_CHECK=$($SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && if [ -f .env.prod ]; then echo 'exists'; else echo 'missing'; fi")

if [ "$ENV_CHECK" = "missing" ]; then
    echo "   ⚠️  Warning: .env.prod not found on server at $DEPLOY_REMOTE_PATH"
    echo "   The deployment will proceed, but ensure .env.prod exists for proper configuration."
    echo "   You can create it manually or upload it with:"
    echo "     scp -P $DEPLOY_PORT .env.prod $SSH_TARGET:$DEPLOY_REMOTE_PATH/"
fi

# 4. Check for docker-compose.prod.yml on server
echo "4. Ensuring docker-compose.prod.yml exists on server..."
COMPOSE_CHECK=$($SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && if [ -f docker-compose.prod.yml ]; then echo 'exists'; else echo 'missing'; fi")

if [ "$COMPOSE_CHECK" = "missing" ]; then
    echo "   Uploading docker-compose.prod.yml..."
    $SCP_CMD "docker-compose.prod.yml" "$SSH_TARGET:$DEPLOY_REMOTE_PATH/"
    echo "   ✅ docker-compose.prod.yml uploaded"
else
    echo "   ✅ docker-compose.prod.yml already exists"
fi

# 5. Stop and remove existing containers
echo "5. Stopping existing containers..."
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml down --remove-orphans 2>/dev/null || true"
echo "   ✅ Existing containers stopped"

# 6. Start new containers with .env.prod
echo "6. Starting new containers with .env.prod..."
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && APP_DOMAIN=cantores.hu docker compose -f docker-compose.prod.yml --env-file .env.prod up -d"

if [ $? -ne 0 ]; then
    echo "❌ Docker compose up failed!"
    echo "   Check logs with: ssh -p $DEPLOY_PORT $SSH_TARGET 'cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml logs'"
    exit 1
fi
echo "   ✅ Containers started successfully"

# 7. Verify services are running
echo "7. Verifying services..."
sleep 5
SERVICES=$($SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml ps --services")
echo "   Running services:"
for service in $SERVICES; do
    STATUS=$($SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml ps $service --format '{{.Status}}'")
    echo "   - $service: $STATUS"
done

echo
echo "=== Deployment Complete ==="
echo "✅ Application deployed successfully to $DEPLOY_SERVER"
echo
echo "Next steps:"
echo "1. Check application logs:"
echo "   ssh -p $DEPLOY_PORT $SSH_TARGET 'cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml logs -f'"
echo
echo "2. Monitor container status:"
echo "   ssh -p $DEPLOY_PORT $SSH_TARGET 'cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml ps'"
echo
echo "3. View Traefik dashboard (if enabled):"
echo "   http://$DEPLOY_SERVER:8080"
echo
echo "4. Clean up old images (optional):"
echo "   ssh -p $DEPLOY_PORT $SSH_TARGET 'docker image prune -f'"
echo
echo "The application should be available at: https://$DEPLOY_SERVER"