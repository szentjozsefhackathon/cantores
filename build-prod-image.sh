DIR=dist
mkdir -p $DIR/deploy/production

FILE_PATH=$DIR/creshu-app-prod.tar.gz
echo $FILE_PATH - Creating  docker image for distribution. 
echo

APP_DOMAIN=cantores.hu GIT_COMMIT_HASH=$(git rev-parse HEAD) docker compose -f docker-compose.prod.yml build app
docker save creshu-app-prod:latest | gzip > $FILE_PATH